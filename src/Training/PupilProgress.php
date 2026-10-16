<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\User;

/**
 * Where a pupil of a class stands, for their teacher: the steps of "Mon parcours" and their flights.
 */
final readonly class PupilProgress
{
    /**
     * @param list<StepView> $steps
     */
    public function __construct(
        public User $pupil,
        public array $steps,
        public int $flights,
        public int $seconds,
        public ?\DateTimeImmutable $lastFlight,
    ) {
    }

    public function hours(): float
    {
        return $this->seconds / 3600;
    }
}
