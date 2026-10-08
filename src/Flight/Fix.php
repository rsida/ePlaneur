<?php

declare(strict_types=1);

namespace App\Flight;

/**
 * One position of a flight track.
 */
final readonly class Fix
{
    /**
     * @param float $time     seconds since the first fix
     * @param float $altitude metres above sea level
     */
    public function __construct(
        public float $time,
        public float $latitude,
        public float $longitude,
        public float $altitude,
    ) {
    }
}
