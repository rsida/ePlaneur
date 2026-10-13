<?php

declare(strict_types=1);

namespace App\Flight;

/**
 * A point of the task. The first one is the departure airfield, the second the start, the last the
 * finish (Condor's convention). Its zone is a cylinder of `radius` (angle 360°) or, for the start
 * and the finish, a half disc of `radius` on one side of the line across the leg (angle 180°):
 * behind it for the start, left when crossing the line; beyond it for the finish, entered when
 * crossing the line. Other angles (FAI sectors) are taken as cylinders. Condor shows a 180° start
 * or finish as a flat gate on the line, between the minimum and the maximum altitude of the point
 * (the maximum start height, the minimum finish altitude of the task).
 */
final readonly class Turnpoint
{
    /**
     * @param float      $altitude    metres above sea level
     * @param float      $radius      metres
     * @param float      $angle       degrees of the zone: 360 for a cylinder, 180 for a line
     * @param float|null $minAltitude metres above sea level, null when the point has none
     * @param float|null $maxAltitude metres above sea level, null when the point has none
     */
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
        public float $altitude,
        public float $radius,
        public float $angle = 360.0,
        public ?float $minAltitude = null,
        public ?float $maxAltitude = null,
    ) {
    }

    public function isLine(): bool
    {
        return abs($this->angle - 180.0) < 1.0;
    }

    /**
     * Metres Condor takes off the last leg when this point is the finish: the radius of a cylinder
     * (measured on the second test flight, 2026-10-12), nothing for a line.
     */
    public function finishCut(): float
    {
        return $this->isLine() ? 0.0 : $this->radius;
    }
}
