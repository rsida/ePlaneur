<?php

declare(strict_types=1);

namespace App\Entity;

use App\Flight\Glider\Polar;
use App\Repository\GliderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A glider of Condor: its sheet and polar, read from the files of the simulator
 * (Planes/<name>/<name>.txt and .pol, App\Flight\Glider\CondorGliders). Its name is the one of the
 * Condor folder, which the tracks and the results of the server use ("Diana2", "JS3-15").
 */
#[ORM\Entity(repositoryClass: GliderRepository::class)]
class Glider
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40, unique: true)]
    private string $name;

    /** Maker and model, e.g. "Avionic Diana-2". */
    #[ORM\Column(length: 120)]
    private string $model;

    /** Metres. */
    #[ORM\Column]
    private float $span;

    /** Square metres. */
    #[ORM\Column]
    private float $wingArea;

    /** Metres. */
    #[ORM\Column(nullable: true)]
    private ?float $length = null;

    /** Kilograms. */
    #[ORM\Column]
    private float $emptyMass;

    /** Kilograms. */
    #[ORM\Column]
    private float $maxMass;

    /** Litres of water ballast it can carry. */
    #[ORM\Column]
    private int $waterBallast = 0;

    /** kg/m², without ballast (the only one of a glider without ballast). */
    #[ORM\Column]
    private float $minWingLoading;

    /** kg/m², full of water. */
    #[ORM\Column]
    private float $maxWingLoading;

    /** Never exceed speed, km/h. */
    #[ORM\Column]
    private float $maxSpeed;

    /** km/h. */
    #[ORM\Column(nullable: true)]
    private ?float $maneuveringSpeed = null;

    /** Stall speed, km/h. */
    #[ORM\Column(nullable: true)]
    private ?float $minSpeed = null;

    /** m/s, as on the sheet. */
    #[ORM\Column(nullable: true)]
    private ?float $minSink = null;

    /** As on the sheet. */
    #[ORM\Column(nullable: true)]
    private ?float $bestGlide = null;

    /** km/h, as on the sheet. */
    #[ORM\Column(nullable: true)]
    private ?float $bestGlideSpeed = null;

    /** Handicap of the German gliding association (Diana 2: 116). */
    #[ORM\Column(nullable: true)]
    private ?int $handicap = null;

    /** What the sheet says of an engine, e.g. "Poussée 420 N, carburant 42 l". */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $engine = null;

    /**
     * Nodes of the polar (Polar::$nodes).
     *
     * @var list<list<float>>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $polar = [];

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $name, \DateTimeImmutable $updatedAt)
    {
        $this->name = $name;
        $this->updatedAt = $updatedAt;
    }

    /**
     * Sets the figures of the sheet and the polar.
     *
     * @param array{model: string, span: float, wingArea: float, length: ?float, emptyMass: float, maxMass: float, waterBallast: int, minWingLoading: float, maxWingLoading: float, maxSpeed: float, maneuveringSpeed: ?float, minSpeed: ?float, minSink: ?float, bestGlide: ?float, bestGlideSpeed: ?float, handicap: ?int, engine: ?string} $sheet
     */
    public function update(array $sheet, Polar $polar, \DateTimeImmutable $now): void
    {
        $this->model = $sheet['model'];
        $this->span = $sheet['span'];
        $this->wingArea = $sheet['wingArea'];
        $this->length = $sheet['length'];
        $this->emptyMass = $sheet['emptyMass'];
        $this->maxMass = $sheet['maxMass'];
        $this->waterBallast = $sheet['waterBallast'];
        $this->minWingLoading = $sheet['minWingLoading'];
        $this->maxWingLoading = $sheet['maxWingLoading'];
        $this->maxSpeed = $sheet['maxSpeed'];
        $this->maneuveringSpeed = $sheet['maneuveringSpeed'];
        $this->minSpeed = $sheet['minSpeed'];
        $this->minSink = $sheet['minSink'];
        $this->bestGlide = $sheet['bestGlide'];
        $this->bestGlideSpeed = $sheet['bestGlideSpeed'];
        $this->handicap = $sheet['handicap'];
        $this->engine = $sheet['engine'];
        $this->polar = $polar->nodes;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getSpan(): float
    {
        return $this->span;
    }

    public function getWingArea(): float
    {
        return $this->wingArea;
    }

    public function getLength(): ?float
    {
        return $this->length;
    }

    public function getEmptyMass(): float
    {
        return $this->emptyMass;
    }

    public function getMaxMass(): float
    {
        return $this->maxMass;
    }

    public function getWaterBallast(): int
    {
        return $this->waterBallast;
    }

    public function getMinWingLoading(): float
    {
        return $this->minWingLoading;
    }

    public function getMaxWingLoading(): float
    {
        return $this->maxWingLoading;
    }

    public function getMaxSpeed(): float
    {
        return $this->maxSpeed;
    }

    public function getManeuveringSpeed(): ?float
    {
        return $this->maneuveringSpeed;
    }

    public function getMinSpeed(): ?float
    {
        return $this->minSpeed;
    }

    public function getMinSink(): ?float
    {
        return $this->minSink;
    }

    public function getBestGlide(): ?float
    {
        return $this->bestGlide;
    }

    public function getBestGlideSpeed(): ?float
    {
        return $this->bestGlideSpeed;
    }

    public function getHandicap(): ?int
    {
        return $this->handicap;
    }

    public function getEngine(): ?string
    {
        return $this->engine;
    }

    public function getPolar(): Polar
    {
        return new Polar($this->polar);
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Mass of the polar: the glider at its minimum wing loading, i.e. empty with a pilot and no water
     * (assumed: Condor does not say at which mass its polars are; the minimum sink and best glide of
     * the curve are those of the sheet).
     */
    public function getReferenceMass(): float
    {
        return $this->minWingLoading * $this->wingArea;
    }

    /** Mass flying with some litres of water (a litre weighs a kilogram), at most the maximum. */
    public function massWith(?int $water): float
    {
        return min($this->maxMass, $this->getReferenceMass() + max(0, $water ?? 0));
    }

    public function wingLoadingWith(?int $water): float
    {
        return $this->massWith($water) / $this->wingArea;
    }
}
