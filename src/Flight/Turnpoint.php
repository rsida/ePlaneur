<?php

declare(strict_types=1);

namespace App\Flight;

/**
 * A point of the task. The first one is the departure airfield, the second the start, the last the
 * finish (Condor's convention). Its zone is a cylinder of `radius` (angle 360°) or, for the start
 * and the finish, a half disc of `radius` behind the line across the leg (angle 180°): the finish is
 * reached when entering it, the start is the last exit. Other angles (FAI sectors) are taken as
 * cylinders.
 */
final readonly class Turnpoint
{
    /**
     * @param float $altitude metres above sea level
     * @param float $radius   metres
     * @param float $angle    degrees of the zone: 360 for a cylinder, 180 for a line
     */
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
        public float $altitude,
        public float $radius,
        public float $angle = 360.0,
    ) {
    }

    public function isLine(): bool
    {
        return abs($this->angle - 180.0) < 1.0;
    }
}
