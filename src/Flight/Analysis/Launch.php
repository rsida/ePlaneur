<?php

declare(strict_types=1);

namespace App\Flight\Analysis;

/**
 * How the flight began, read from the beginning of the track (FlightAnalyzer::launch()).
 */
enum Launch: string
{
    case Airborne = 'airborne';
    case Aerotow = 'aerotow';
    case Winch = 'winch';

    public function label(): string
    {
        return match ($this) {
            self::Airborne => 'Départ en vol',
            self::Aerotow => 'Remorquage',
            self::Winch => 'Treuil',
        };
    }
}
