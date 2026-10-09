<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the session form edits (SessionType): the flight plan is typed as a text, with the address of
 * its sheet, and recorded when it is new (FlightPlans).
 */
final class SessionData
{
    #[Assert\NotNull(message: 'Indiquez la date de la session.')]
    public ?\DateTimeImmutable $date = null;

    /** Required for a new session (SessionType); unknown for the history of eplaneur.fr. */
    public ?\DateTimeImmutable $startTime = null;

    #[Assert\NotBlank(message: 'Indiquez le plan de vol.')]
    #[Assert\Regex(FlightPlan::CODE_PATTERN, message: 'Indiquez le code du plan de vol de la bibliothèque eplaneur.fr, par exemple C3-c046a.')]
    #[Assert\Length(max: 200)]
    public ?string $flightPlan = null;

    #[Assert\Url(requireTld: true, message: 'Cette adresse n’est pas valide.')]
    #[Assert\Length(max: 255)]
    public ?string $sheetUrl = null;

    #[Assert\Length(max: 60)]
    public ?string $dedicatedTo = null;

    #[Assert\Url(requireTld: true, message: 'Cette adresse n’est pas valide.')]
    #[Assert\Length(max: 255)]
    public ?string $condorClubUrl = null;

    #[Assert\Length(max: 3000)]
    public ?string $notes = null;

    #[Assert\File(maxSize: '1M', extensions: ['csv', 'txt'], extensionsMessage: 'Déposez le fichier CSV écrit par le serveur Condor.')]
    public ?UploadedFile $results = null;

    #[Assert\File(maxSize: '1M', extensions: ['fpl'], extensionsMessage: 'Déposez le plan de vol Condor (fichier .fpl).')]
    public ?UploadedFile $flightPlanFile = null;

    public static function of(NetworkSession $session): self
    {
        $data = new self();
        $data->date = $session->getDate();
        $data->startTime = $session->getStartTime();
        $data->flightPlan = $session->getFlightPlan()->getLabel();
        $data->sheetUrl = $session->getFlightPlan()->getSheetUrl();
        $data->dedicatedTo = $session->getDedicatedTo();
        $data->condorClubUrl = $session->getCondorClubUrl();
        $data->notes = $session->getNotes();

        return $data;
    }

    /** The route typed after the code: "C3-c046a · CdF2025..." or the Condor task name. */
    public function flightPlanTitle(): ?string
    {
        $text = trim((string) $this->flightPlan);
        if (preg_match('/^\S+\s*[·:–-]\s+(.+)$/u', $text, $match)) {
            return trim($match[1]);
        }

        return FlightPlan::describe($text)['title'];
    }
}
