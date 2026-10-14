<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\PathCompletion;
use App\Entity\PathItem;

/**
 * An item of "Mon parcours" for a pilot: done or not, and what shows it.
 */
final readonly class ItemView
{
    /**
     * @param list<ExerciseResult> $results the pilot's flights on the flight plan of an exercise, checked, the latest first
     */
    public function __construct(
        public PathItem $item,
        public bool $done,
        public ?PathCompletion $completion = null,
        public array $results = [],
    ) {
    }

    /** The pilot's best flight that passed the exercise: their personal best. */
    public function best(): ?ExerciseResult
    {
        return ExerciseRanking::best(array_filter($this->results, static fn (ExerciseResult $result): bool => $result->passed()), $this->item->getRankBy());
    }

    /** @return list<ExerciseResult> */
    public function passed(): array
    {
        return array_values(array_filter($this->results, static fn (ExerciseResult $result): bool => $result->passed()));
    }
}
