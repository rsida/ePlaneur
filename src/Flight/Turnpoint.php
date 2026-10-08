<?php

declare(strict_types=1);

namespace App\Flight;

/**
 * A point of the task. The first one is the departure airfield, the second the start, the last the
 * finish (Condor's convention).
 */
final readonly class Turnpoint
{
    /**
     * @param float $altitude metres above sea level
     * @param float $radius   metres
     */
    public function __construct(
        public string $name,
        public float $latitude,
        public float $longitude,
        public float $altitude,
        public float $radius,
    ) {
    }
}
