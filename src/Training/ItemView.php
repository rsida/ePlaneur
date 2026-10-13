<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\Flight;
use App\Entity\PathCompletion;
use App\Entity\PathItem;

/**
 * An item of "Mon parcours" for a pilot: done or not, and what shows it.
 */
final readonly class ItemView
{
    /**
     * @param list<Flight> $flights the pilot's finished tasks on the flight plan of an exercise, the fastest first
     */
    public function __construct(
        public PathItem $item,
        public bool $done,
        public ?PathCompletion $completion = null,
        public array $flights = [],
    ) {
    }

    /** The pilot's best flight on the exercise: their personal best. */
    public function best(): ?Flight
    {
        return $this->flights[0] ?? null;
    }
}
