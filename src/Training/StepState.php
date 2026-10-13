<?php

declare(strict_types=1);

namespace App\Training;

/**
 * Where a step of "Mon parcours" stands for a pilot.
 */
enum StepState: string
{
    /** Every item done, or validated by a trainer. */
    case Done = 'done';
    /** The first step not done: the one to work on. */
    case Current = 'current';
    /** Open, but a step before it is not done yet. */
    case Locked = 'locked';
    /** Not open yet in the back-office: content to come. */
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Done => 'Terminée',
            self::Current => 'En cours',
            self::Locked => 'À débloquer',
            self::Closed => 'Bientôt',
        };
    }
}
