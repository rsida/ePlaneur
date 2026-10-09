<?php

declare(strict_types=1);

namespace App\Training;

/**
 * Where a pilot stands on a step of the training path; a step without progress is not started.
 */
enum ProgressStatus: string
{
    case InProgress = 'in_progress';
    case Validated = 'validated';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En cours',
            self::Validated => 'Validée',
        };
    }
}
