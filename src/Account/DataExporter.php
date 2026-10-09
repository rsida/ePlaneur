<?php

declare(strict_types=1);

namespace App\Account;

use App\Entity\ContactRequest;
use App\Entity\FlightComment;
use App\Entity\User;
use App\Flight\FlightImporter;
use App\Repository\FlightRepository;
use App\Repository\MembershipRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Archive of what the site holds about an account (GDPR right of access and portability): a ZIP
 * file with JSON files (account, memberships, flights, comments, messages to the club) and the
 * flight tracks as uploaded. The password is not in it.
 */
final readonly class DataExporter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FlightRepository $flights,
        private MembershipRepository $memberships,
        private FlightImporter $importer,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Writes the archive to a temporary file and returns its path (the caller sends and removes it).
     */
    public function export(User $user): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'eplaneur-export');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $json = static fn (mixed $data): string => (string) json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $date = static fn (?\DateTimeInterface $at): ?string => $at?->format(\DATE_ATOM);

        $zip->addFromString('compte.json', $json([
            'email' => $user->getEmail(),
            'nom_affiche' => $user->getDisplayName(),
            'cree_le' => $date($user->getCreatedAt()),
            'groupes' => $user->getGroups()->map(static fn ($group): string => $group->getName())->getValues(),
            'cn_condor' => $user->getCondorCn(),
            'nom_pilote_condor' => $user->getCondorPilotName(),
            'versions_condor' => $user->getCondorVersions(),
            'e_mails_acceptes' => array_map(static fn ($notification): string => $notification->label(), $user->getNotifications()),
            'vols_publics_par_defaut' => $user->isFlightsPublicByDefault(),
            'dans_le_classement' => $user->isInRanking(),
        ]));

        $zip->addFromString('adhesions.json', $json(array_map(static fn ($membership): array => [
            'saison' => $membership->getSeason()->getYear(),
            'type' => $membership->getType()?->getName(),
            'etat' => $membership->getStatus()->label(),
            'cotisation_euros' => $membership->getFee() / 100,
            'soutien_euros' => $membership->getDonation() / 100,
            'prenom' => $membership->getFirstName(),
            'nom' => $membership->getLastName(),
            'date_de_naissance' => $membership->getBirthDate()?->format('Y-m-d'),
            'adresse' => $membership->getAddress(),
            'code_postal' => $membership->getPostalCode(),
            'ville' => $membership->getCity(),
            'pays' => $membership->getCountry(),
            'telephone' => $membership->getPhone(),
            'demandee_le' => $date($membership->getRequestedAt()),
            'payee_le' => $date($membership->getPaidAt()),
            'validee_le' => $date($membership->getValidatedAt()),
        ], $this->memberships->findByUser($user))));

        $flights = [];
        foreach ($this->flights->findBy(['owner' => $user], ['uploadedAt' => 'ASC']) as $flight) {
            $file = null;
            if ('' !== $flight->getFileName() && is_file($this->importer->pathOf($flight))) {
                $file = \sprintf('traces/%d-%s', $flight->getId(), preg_replace('/[^A-Za-z0-9._-]/', '_', $flight->getOriginalName()));
                $zip->addFile($this->importer->pathOf($flight), $file);
            }
            $flights[] = [
                'pilote' => $flight->getPilot(),
                'cn' => $flight->getCompetitionId(),
                'planeur' => $flight->getGlider(),
                'paysage' => $flight->getLandscape(),
                'plan_de_vol' => $flight->getFlightPlan(),
                'date' => $flight->getDate()->format('Y-m-d'),
                'duree_secondes' => $flight->getDuration(),
                'distance_metres' => $flight->getDistance(),
                'vitesse_circuit_kmh' => $flight->getTaskSpeed(),
                'public' => $flight->isPublic(),
                'deposee_le' => $date($flight->getUploadedAt()),
                'fichier' => $file,
            ];
        }
        $zip->addFromString('vols.json', $json($flights));

        $zip->addFromString('commentaires.json', $json(array_map(static fn (FlightComment $comment): array => [
            'vol' => $comment->getFlight()->getFlightPlan() ?? $comment->getFlight()->getPilot(),
            'instant_secondes' => $comment->getAt(),
            'etape' => $comment->getStep()?->title(),
            'texte' => $comment->getBody(),
            'ecrit_le' => $date($comment->getCreatedAt()),
        ], $this->entityManager->getRepository(FlightComment::class)->findBy(['author' => $user], ['createdAt' => 'ASC']))));

        $zip->addFromString('messages_au_club.json', $json(array_map(static fn (ContactRequest $request): array => [
            'objet' => $request->getSubject()?->label(),
            'message' => $request->getMessage(),
            'envoye_le' => $date($request->getCreatedAt()),
        ], $this->entityManager->getRepository(ContactRequest::class)->findBy(['user' => $user], ['createdAt' => 'ASC']))));

        $zip->addFromString('LISEZ-MOI.txt', 'Données de votre compte sur le site du Club ePlaneur, exportées le '.$this->clock->now()->format('d/m/Y à H:i').".\nFichiers JSON lisibles avec un éditeur de texte ; vos traces de vol sont dans le dossier « traces ».\nVotre mot de passe n'y figure pas.\n");
        $zip->close();

        return $path;
    }
}
