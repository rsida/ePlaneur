<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Flight values for people: `3725|flight_duration` → "1 h 02 min", `182408|flight_distance` →
 * "182,4 km".
 */
final class FlightExtension
{
    #[AsTwigFilter('flight_duration')]
    public static function duration(int|float|null $seconds): string
    {
        if (null === $seconds) {
            return '—';
        }
        $minutes = (int) round($seconds / 60);

        return $minutes >= 60 ? \sprintf('%d h %02d min', intdiv($minutes, 60), $minutes % 60) : \sprintf('%d min', $minutes);
    }

    #[AsTwigFilter('flight_distance')]
    public static function distance(int|float|null $metres): string
    {
        return null === $metres ? '—' : number_format($metres / 1000, 1, ',', "\u{202F}").' km';
    }
}
