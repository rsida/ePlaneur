<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\PathStep;
use App\Entity\StepProgress;

/**
 * A step of "Mon parcours" for a pilot: its state, its items and the trainer's mark.
 */
final readonly class StepView
{
    /**
     * @param list<ItemView> $items
     */
    public function __construct(
        public PathStep $step,
        public StepState $state,
        public array $items,
        public ?StepProgress $progress = null,
    ) {
    }

    public function doneCount(): int
    {
        return \count(array_filter($this->items, static fn (ItemView $item): bool => $item->done));
    }

    /** Items can be done in a step that is not closed nor locked. */
    public function isReachable(): bool
    {
        return StepState::Done === $this->state || StepState::Current === $this->state;
    }

    /** Share of the items done, in percent. */
    public function percent(): int
    {
        if (StepState::Done === $this->state) {
            return 100;
        }

        return [] === $this->items ? 0 : (int) floor(100 * $this->doneCount() / \count($this->items));
    }
}
