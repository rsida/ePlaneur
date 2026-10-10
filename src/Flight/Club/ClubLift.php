<?php

declare(strict_types=1);

namespace App\Flight\Club;

use App\Entity\Flight;

/**
 * Where the lift was on a landscape, from every club flight on it: the centre of each thermal
 * (where its pilot circled) with its climb and altitudes, and each slope lift phase (straight lift
 * that is not wave, `phase.wave`) as a line with the wind of its flight, so that the slopes that
 * work can be read by wind direction. Wind: the task of the session (base weather zone), else the
 * wind the relief was read with, else the drift of the thermals.
 */
final class ClubLift
{
    /** Seconds between the points of a slope line. */
    private const int SLOPE_STEP = 5;

    /**
     * @param list<Flight> $flights of one landscape
     *
     * @return array{thermals: list<array{float, float, float, int, int}>, slopes: list<array{windFrom: ?float, windSpeed: ?float, climb: float, gain: float, path: list<array{float, float}>}>, flights: int}
     */
    public static function of(array $flights): array
    {
        $thermals = [];
        $slopes = [];
        foreach ($flights as $flight) {
            $analysis = $flight->getAnalysis();
            $series = $analysis['series'];
            $wind = self::windOf($flight);
            foreach ($analysis['phases'] as $phase) {
                if ($phase['end'] <= $phase['start']) {
                    continue;
                }
                if ('thermal' === $phase['type']) {
                    $length = $phase['end'] - $phase['start'] + 1;
                    $thermals[] = [
                        round(array_sum(\array_slice($series['latitude'], $phase['start'], $length)) / $length, 5),
                        round(array_sum(\array_slice($series['longitude'], $phase['start'], $length)) / $length, 5),
                        round((float) $phase['vario'], 1),
                        (int) round($series['altitude'][$phase['start']]),
                        (int) round($series['altitude'][$phase['end']]),
                    ];
                } elseif ('lift' === $phase['type'] && true !== ($phase['wave'] ?? null)) {
                    $path = [];
                    for ($i = $phase['start']; $i <= $phase['end']; $i += self::SLOPE_STEP) {
                        $path[] = [round($series['longitude'][$i], 5), round($series['latitude'][$i], 5)];
                    }
                    $slopes[] = ['windFrom' => $wind['from'] ?? null, 'windSpeed' => $wind['speed'] ?? null, 'climb' => round((float) $phase['vario'], 1), 'gain' => (float) $phase['gain'], 'path' => $path];
                }
            }
        }

        return ['thermals' => $thermals, 'slopes' => $slopes, 'flights' => \count($flights)];
    }

    /**
     * @return array{from: float, speed: float}|null
     */
    private static function windOf(Flight $flight): ?array
    {
        $base = $flight->getSession()?->getTaskSettings()?->weather[0] ?? null;
        if (null !== $base) {
            return ['from' => $base['windDirection'], 'speed' => $base['windSpeed']];
        }
        $analysis = $flight->getAnalysis();
        $wind = $analysis['slopes']['wind'] ?? null;
        if (\is_array($wind)) {
            return ['from' => (float) $wind['from'], 'speed' => (float) $wind['speed']];
        }
        $statistics = $analysis['statistics'];

        return isset($statistics['windFrom'], $statistics['windSpeed']) ? ['from' => (float) $statistics['windFrom'], 'speed' => (float) $statistics['windSpeed']] : null;
    }
}
