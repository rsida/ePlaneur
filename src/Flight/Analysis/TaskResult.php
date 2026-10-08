<?php

declare(strict_types=1);

namespace App\Flight\Analysis;

use App\Flight\Turnpoint;

/**
 * How the task was flown: when each point was reached, and the legs between them. Turnpoints are
 * taken as cylinders of their radius (Condor's sectors are approximated).
 */
final readonly class TaskResult
{
    /**
     * @param list<Turnpoint> $turnpoints
     * @param list<int|null>  $reached    index in the 1 s series where each point was reached (the
     *                                    departure airfield has none; the start is the last exit)
     * @param float           $distance   metres from the start to the finish, through the centres
     */
    public function __construct(
        public array $turnpoints,
        public array $reached,
        public float $distance,
    ) {
    }

    public function start(): ?int
    {
        return $this->reached[1] ?? null;
    }

    public function finish(): ?int
    {
        return [] !== $this->reached ? $this->reached[array_key_last($this->reached)] : null;
    }

    public function isCompleted(): bool
    {
        return null !== $this->start() && null !== $this->finish();
    }
}
