<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NetworkSessionRepository;
use App\Session\TaskSettings;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A network session: the pilots of the club fly one flight plan together on a Condor server.
 *
 * Its results come from the dedicated server (the "DSRace-<race id>-on-<date>.csv" file), and the
 * tracks pilots upload join it by the race id written in their .ftr file (Flight::$session).
 */
#[ORM\Entity(repositoryClass: NetworkSessionRepository::class)]
#[ORM\Index(columns: ['date'])]
#[ORM\Index(columns: ['race_id'])]
class NetworkSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private FlightPlan $flightPlan;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    /** Time of the briefing, metropolitan France time; unknown for the history of eplaneur.fr. */
    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startTime = null;

    /** Who a dedicated session is for ("Epitech", "Compétition"...); null: open to everyone. */
    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $dedicatedTo = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $organiser = null;

    /** Circuit of the session on Condor Club, where pilots record their performance. */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true)]
    #[Assert\Length(max: 255)]
    private ?string $condorClubUrl = null;

    /** Practical details for the pilots: server, Stop Join, start, briefing channel... */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 3000)]
    private ?string $notes = null;

    /** Race id of the dedicated server, also in the .ftr of every pilot of the race. */
    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $raceId = null;

    /** Name of the results file of the server, e.g. "DSRace-971793592-on-2026-10-08". */
    #[ORM\Column(length: 80, unique: true, nullable: true)]
    private ?string $resultsFile = null;

    /**
     * Results of the server, in their order (App\Session\SessionResults).
     *
     * @var list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $results = [];

    /**
     * Settings of the task (TaskSettings::toArray()), from its Condor Club page or its .fpl file.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $taskSettings = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Flight> */
    #[ORM\OneToMany(targetEntity: Flight::class, mappedBy: 'session')]
    private Collection $flights;

    public function __construct(FlightPlan $flightPlan, \DateTimeImmutable $date, \DateTimeImmutable $createdAt)
    {
        $this->flightPlan = $flightPlan;
        $this->date = $date->setTime(0, 0);
        $this->createdAt = $createdAt;
        $this->flights = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFlightPlan(): FlightPlan
    {
        return $this->flightPlan;
    }

    public function setFlightPlan(FlightPlan $flightPlan): static
    {
        $this->flightPlan = $flightPlan;

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date->setTime(0, 0);

        return $this;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeImmutable $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    /** Date and time of the session (midnight when the time is not known). */
    public function getStartsAt(): \DateTimeImmutable
    {
        return null !== $this->startTime
            ? $this->date->setTime((int) $this->startTime->format('H'), (int) $this->startTime->format('i'))
            : $this->date;
    }

    public function getDedicatedTo(): ?string
    {
        return $this->dedicatedTo;
    }

    public function setDedicatedTo(?string $dedicatedTo): static
    {
        $this->dedicatedTo = null !== $dedicatedTo && '' !== trim($dedicatedTo) ? trim($dedicatedTo) : null;

        return $this;
    }

    public function getOrganiser(): ?User
    {
        return $this->organiser;
    }

    public function setOrganiser(?User $organiser): static
    {
        $this->organiser = $organiser;

        return $this;
    }

    public function getCondorClubUrl(): ?string
    {
        return $this->condorClubUrl;
    }

    public function setCondorClubUrl(?string $condorClubUrl): static
    {
        $this->condorClubUrl = $condorClubUrl;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getRaceId(): ?int
    {
        return $this->raceId;
    }

    public function getResultsFile(): ?string
    {
        return $this->resultsFile;
    }

    /**
     * The results of the server and the file they come from; the race id is read from its name.
     *
     * @param list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}> $results
     */
    public function setResults(string $resultsFile, array $results): static
    {
        $this->resultsFile = $resultsFile;
        $this->raceId = preg_match('/Race-(\d+)-on-/', $resultsFile, $match) ? (int) $match[1] : null;
        $this->results = $results;

        return $this;
    }

    /**
     * @return list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function getTaskSettings(): ?TaskSettings
    {
        return null !== $this->taskSettings ? TaskSettings::fromArray($this->taskSettings) : null;
    }

    public function setTaskSettings(?TaskSettings $settings): static
    {
        $this->taskSettings = $settings?->toArray();

        return $this;
    }

    /** Id of the task on Condor Club, read from the address of its page (condor.club/task/<id>). */
    public function getCondorClubTaskId(): ?int
    {
        return null !== $this->condorClubUrl && preg_match('#condor\.club/(?:[a-z]{2}/)?task/(\d+)#i', $this->condorClubUrl, $match) ? (int) $match[1] : null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Flight> */
    public function getFlights(): Collection
    {
        return $this->flights;
    }

    /** Number of pilots: the results of the server, or the tracks uploaded when there are none. */
    public function getParticipantCount(): int
    {
        return [] !== $this->results ? \count($this->results) : $this->flights->count();
    }

    public function isPast(\DateTimeImmutable $now): bool
    {
        return $this->date < $now->setTime(0, 0);
    }
}
