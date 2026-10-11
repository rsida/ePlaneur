<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Repository\NetworkSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Records the sessions proposed on the site and their results.
 */
final readonly class SessionManager
{
    public function __construct(
        private FlightPlans $flightPlans,
        private NetworkSessionRepository $sessions,
        private CondorClubTasks $condorClub,
        private SessionLinker $linker,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Creates the session (organised by $organiser) or updates it from the form, records its flight
     * plan when new, reads the results file if one was sent and gathers the tracks.
     *
     * @throws InvalidSessionException when the flight plan has no code or the results file cannot be read
     */
    public function save(SessionData $data, ?NetworkSession $session, ?User $organiser): NetworkSession
    {
        $code = FlightPlan::codeIn($data->flightPlan) ?? throw new InvalidSessionException('Indiquez le code du plan de vol, par exemple C3-c046a.', 'flightPlan');
        $described = FlightPlan::describe((string) $data->flightPlan);
        $plan = $this->flightPlans->get($code, $data->flightPlanTitle(), $described['gliders'], $described['landscape'], $data->sheetUrl);
        \assert(null !== $data->date);

        if (null === $session) {
            $session = new NetworkSession($plan, $data->date, $this->clock->now());
            $session->setOrganiser($organiser);
            $this->entityManager->persist($session);
        }
        $session->setFlightPlan($plan)
            ->setDate($data->date)
            ->setStartTime($data->startTime)
            ->setDedicatedTo($data->dedicatedTo)
            ->setCondorClubUrl($data->condorClubUrl)
            ->setJoinTime($data->joinTime)
            ->setNotes($data->notes);

        if (null !== $data->flightPlanFile) {
            try {
                $session->setTaskSettings(TaskSettings::fromFpl((string) file_get_contents($data->flightPlanFile->getPathname())));
            } catch (\InvalidArgumentException) {
                throw new InvalidSessionException('Ce fichier n’est pas un plan de vol Condor (.fpl).', 'flightPlanFile');
            }
        }
        if (null !== $data->results) {
            $name = pathinfo($data->results->getClientOriginalName(), \PATHINFO_FILENAME);
            if (!preg_match('/^[A-Za-z]*Race-\d+-on-\d{4}-\d{2}-\d{2}$/', $name)) {
                throw new InvalidSessionException('Déposez le fichier tel que le serveur l’a nommé, par exemple DSRace-971793592-on-2026-10-08.csv : son nom donne le numéro de la course.', 'results');
            }
            $other = $this->sessions->findOneBy(['resultsFile' => $name]);
            if (null !== $other && $other !== $session) {
                throw new InvalidSessionException(\sprintf('Ces résultats sont déjà ceux de la session du %s.', $other->getDate()->format('d/m/Y')), 'results');
            }
            try {
                $results = SessionResults::fromCsv((string) file_get_contents($data->results->getPathname()));
            } catch (\InvalidArgumentException) {
                throw new InvalidSessionException('Ce fichier ne contient pas le tableau des résultats du serveur (colonnes Rank, Status, Player…).', 'results');
            }
            $session->setResults($name, $results);
        }
        $this->linker->gather($session);
        $this->entityManager->flush();

        return $session;
    }

    /**
     * Takes the settings of the task from its Condor Club page, when the session links to one;
     * false when Condor Club could not give them (the caller flushes).
     */
    public function fetchSettings(NetworkSession $session): bool
    {
        $id = $session->getCondorClubTaskId();
        if (null === $id) {
            return false;
        }
        try {
            $session->setTaskSettings($this->condorClub->settings($id));
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function delete(NetworkSession $session): void
    {
        foreach ($session->getFlights() as $flight) {
            $flight->setSession(null);
        }
        $this->entityManager->remove($session);
        $this->entityManager->flush();
    }
}
