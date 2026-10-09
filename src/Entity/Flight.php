<?php

declare(strict_types=1);

namespace App\Entity;

use App\Flight\Analysis\FlightAnalysis;
use App\Flight\Analysis\Launch;
use App\Repository\FlightRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A flight track uploaded to the flight viewer: what was read from the file, the analysis shown by
 * the viewer (JSON) and the stored file (FlightImporter). Flights of the same Condor race share
 * their race id.
 */
#[ORM\Entity(repositoryClass: FlightRepository::class)]
#[ORM\Index(columns: ['race_id'])]
class Flight
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $owner = null;

    #[ORM\Column(length: 100)]
    private string $pilot;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $competitionId;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $glider;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $landscape;

    #[ORM\Column(length: 30)]
    private string $simulator;

    #[ORM\Column(length: 5)]
    private string $format;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $raceId;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $flightPlan;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $flownOn;

    /** Seconds between take-off and landing. */
    #[ORM\Column]
    private int $duration;

    /** Metres flown. */
    #[ORM\Column]
    private int $distance;

    /** Task speed in km/h, when the task was completed. */
    #[ORM\Column(nullable: true)]
    private ?float $taskSpeed;

    #[ORM\Column(length: 10, nullable: true, enumType: Launch::class)]
    private ?Launch $launch;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $analysis;

    #[ORM\Column(length: 80)]
    private string $fileName;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    /** Shown to visitors too (chosen by the pilot): showcase of the club. */
    #[ORM\Column(options: ['default' => false])]
    private bool $public = false;

    /** Network session the flight was flown in (same race id, see App\Session\SessionLinker). */
    #[ORM\ManyToOne(inversedBy: 'flights')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?NetworkSession $session = null;

    /** @var Collection<int, FlightComment> */
    #[ORM\OneToMany(targetEntity: FlightComment::class, mappedBy: 'flight')]
    #[ORM\OrderBy(['at' => 'ASC', 'createdAt' => 'ASC'])]
    private Collection $comments;

    public function __construct(FlightAnalysis $analysis, string $fileName, string $originalName, ?User $owner, \DateTimeImmutable $uploadedAt)
    {
        $this->comments = new ArrayCollection();
        $this->setAnalysis($analysis);
        $this->fileName = $fileName;
        $this->originalName = mb_substr($originalName, 0, 255);
        $this->owner = $owner;
        $this->uploadedAt = $uploadedAt;
    }

    /** What was read from the file and computed from it: on upload, and when the analysis improves. */
    public function setAnalysis(FlightAnalysis $analysis): void
    {
        $track = $analysis->track;
        $this->pilot = mb_substr($track->pilot ?: 'Pilote inconnu', 0, 100);
        $this->competitionId = null !== $track->competitionId ? mb_substr($track->competitionId, 0, 10) : null;
        $this->glider = null !== $track->glider ? mb_substr($track->glider, 0, 60) : null;
        $this->landscape = $track->landscape;
        $this->simulator = $track->simulator;
        $this->format = $track->format;
        $this->raceId = $track->raceId;
        $this->flightPlan = null !== $track->flightPlan ? mb_substr($track->flightPlan, 0, 160) : null;
        $this->flownOn = $track->date;
        $this->analysis = $analysis->toArray();
        $this->duration = (int) $analysis->statistics['duration'];
        $this->distance = (int) $analysis->statistics['distance'];
        $this->taskSpeed = $this->analysis['task']['speed'] ?? null;
        $this->launch = $analysis->launch;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function getPilot(): string
    {
        return $this->pilot;
    }

    public function getCompetitionId(): ?string
    {
        return $this->competitionId;
    }

    public function getGlider(): ?string
    {
        return $this->glider;
    }

    public function getLandscape(): ?string
    {
        return $this->landscape;
    }

    public function getSimulator(): string
    {
        return $this->simulator;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getRaceId(): ?int
    {
        return $this->raceId;
    }

    public function getFlightPlan(): ?string
    {
        return $this->flightPlan;
    }

    public function getFlownOn(): ?\DateTimeImmutable
    {
        return $this->flownOn;
    }

    public function getDuration(): int
    {
        return $this->duration;
    }

    public function getDistance(): int
    {
        return $this->distance;
    }

    public function getLaunch(): ?Launch
    {
        return $this->launch;
    }

    public function getTaskSpeed(): ?float
    {
        return $this->taskSpeed;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalysis(): array
    {
        return $this->analysis;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /** Name shown for a flight kept after its pilot deleted their account. */
    public const string ANONYMOUS_PILOT = 'Pilote anonyme';

    /**
     * The pilot deleted their account and chose to keep their flights for the club: the analysis
     * stays, without name, competition id or account; the file (which holds the name) is removed
     * by the caller, so the flight can no longer be analysed again.
     */
    public function anonymize(): void
    {
        $this->owner = null;
        $this->pilot = self::ANONYMOUS_PILOT;
        $this->competitionId = null;
        $this->public = false;
        $this->fileName = '';
        $this->originalName = '';
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): static
    {
        $this->public = $public;

        return $this;
    }

    /**
     * Day of the flight: the date in the file, else the day of its session, else the upload date
     * (a .ftr file has no date).
     */
    public function getDate(): \DateTimeImmutable
    {
        return $this->flownOn ?? $this->session?->getDate() ?? $this->uploadedAt;
    }

    public function getSession(): ?NetworkSession
    {
        return $this->session;
    }

    public function setSession(?NetworkSession $session): static
    {
        $this->session = $session;

        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    /**
     * Comments by the moment of the flight they are about (general ones first), then by date.
     *
     * @return Collection<int, FlightComment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }
}
