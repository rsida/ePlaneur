<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Flight values for people: `3725|flight_duration` → "1 h 02 min", `182408|flight_distance` →
 * "182,4 km", `125|flight_gap` → "+2 min 05 s", `95|flight_time` → "1 min 35 s".
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

    /** A time gained or lost, signed: "+2 min 05 s", "−45 s", "0 s". */
    #[AsTwigFilter('flight_gap')]
    public static function gap(int|float|null $seconds): string
    {
        if (null === $seconds) {
            return '—';
        }
        $sign = match (true) {
            $seconds > 0 => '+',
            $seconds < 0 => "\u{2212}",
            default => '',
        };

        return $sign.self::time(abs($seconds));
    }

    /** A short time to the second: "1 h 02 min", "2 min 05 s", "45 s". */
    #[AsTwigFilter('flight_time')]
    public static function time(int|float|null $seconds): string
    {
        if (null === $seconds) {
            return '—';
        }
        $seconds = (int) round($seconds);

        return match (true) {
            $seconds >= 3600 => self::duration($seconds),
            $seconds >= 60 => \sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60),
            default => $seconds.' s',
        };
    }
}
