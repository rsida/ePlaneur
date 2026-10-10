<?php

declare(strict_types=1);

namespace App\Flight;

/**
 * One position of a flight track.
 */
final readonly class Fix
{
    /**
     * @param float      $time     seconds since the first fix
     * @param float      $altitude metres above sea level
     * @param float|null $bank     degrees, positive to the right (Condor 3 .ftr only)
     * @param float|null $pitch    degrees, positive nose up (Condor 3 .ftr only)
     */
    public function __construct(
        public float $time,
        public float $latitude,
        public float $longitude,
        public float $altitude,
        public ?float $bank = null,
        public ?float $pitch = null,
    ) {
    }
}
