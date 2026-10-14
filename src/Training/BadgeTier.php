<?php

declare(strict_types=1);

namespace App\Training;

/**
 * Tiers of a badge of "Mon parcours", in the order they are earned.
 */
enum BadgeTier: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';

    public function label(): string
    {
        return match ($this) {
            self::Bronze => 'Bronze',
            self::Silver => 'Argent',
            self::Gold => 'Or',
        };
    }
}
