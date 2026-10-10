<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;

/**
 * The lift that really existed around a flight, as the other pilots of the same race found it:
 * Condor does not record its thermals, but every pilot of a network race flies in the same air, so
 * where one of them climbed there was a thermal (or slope lift crossed straight). Their thermals
 * whose centres are less than SAME_THERMAL metres apart are one; for each, how close the flight
 * came, and whether it was missed: passed within NEAR metres of its centre, at an altitude where
 * it worked (from BELOW_BOTTOM metres under its lowest entry to its highest exit), without circling
 * in it nor crossing lift around then (within CROSSED_MARGIN seconds: flown through in dolphin
 * flight, it was used), when it climbed MIN_CLIMB m/s or more.
 *
 * The recordings of the pilots do not share a clock: the lift is shown whatever the time, and a
 * thermal can have died (or not be born yet) when the flight passed.
 */
final class SessionLift
{
    private const float SAME_THERMAL = 600.0;
    private const float NEAR = 500.0;
    private const float BELOW_BOTTOM = 200.0;
    private const float MIN_CLIMB = 1.0;
    private const int CROSSED_MARGIN = 30;
    private const float METRES_PER_DEGREE = 111320.0;

    /**
     * @param array<string, mixed> $analysis the flight (Flight::getAnalysis())
     * @param list<Flight>         $others   the other flights of the same race
     *
     * @return array{thermals: list<array<string, mixed>>, crossings: list<array<string, mixed>>}
     */
    public static function around(array $analysis, array $others): array
    {
        $groups = [];
        $crossings = [];
        foreach ($others as $other) {
            $theirs = $other->getAnalysis();
            $series = $theirs['series'];
            $pilot = $other->getCompetitionId() ?? $other->getPilot();
            foreach ($theirs['phases'] as $phase) {
                if ('thermal' !== $phase['type'] || $phase['end'] <= $phase['start']) {
                    continue;
                }
                [$latitude, $longitude] = self::centre($series, $phase['start'], $phase['end']);
                $seconds = $phase['end'] - $phase['start'];
                $key = self::groupOf($groups, $latitude, $longitude);
                if (null === $key) {
                    $groups[] = ['latitude' => $latitude, 'longitude' => $longitude, 'bottom' => $series['altitude'][$phase['start']], 'top' => $series['altitude'][$phase['end']], 'climb' => $phase['vario'] * $seconds, 'seconds' => $seconds, 'pilots' => [$pilot]];
                    continue;
                }
                $group = &$groups[$key];
                $group['bottom'] = min($group['bottom'], $series['altitude'][$phase['start']]);
                $group['top'] = max($group['top'], $series['altitude'][$phase['end']]);
                $group['climb'] += $phase['vario'] * $seconds;
                $group['seconds'] += $seconds;
                $group['pilots'] = array_values(array_unique([...$group['pilots'], $pilot]));
                unset($group);
            }
            foreach ($theirs['crossings'] ?? [] as $crossing) {
                $k = intdiv($crossing['start'] + $crossing['end'], 2);
                $crossings[] = [
                    'latitude' => round($series['latitude'][$k], 6),
                    'longitude' => round($series['longitude'][$k], 6),
                    'altitude' => round($series['altitude'][$k]),
                    'climb' => $crossing['vario'],
                    'gain' => $crossing['gain'],
                    'pilot' => $pilot,
                ];
            }
        }

        $series = $analysis['series'];
        $takeoff = (int) ($analysis['statistics']['takeoff'] ?? 0);
        $landing = (int) ($analysis['statistics']['landing'] ?? \count($series['latitude']) - 1);
        $own = [];
        foreach ($analysis['phases'] as $phase) {
            if ('thermal' === $phase['type'] && $phase['end'] > $phase['start']) {
                $own[] = self::centre($series, $phase['start'], $phase['end']);
            }
        }

        $thermals = [];
        foreach ($groups as $group) {
            $climb = $group['seconds'] > 0 ? $group['climb'] / $group['seconds'] : 0.0;
            [$closest, $distance] = self::closest($series, $takeoff, $landing, $group['latitude'], $group['longitude']);
            $used = [] !== array_filter($own, static fn (array $centre): bool => self::metres($centre[0], $centre[1], $group['latitude'], $group['longitude']) < self::SAME_THERMAL);
            $altitude = $series['altitude'][$closest];
            $crossed = [] !== array_filter($analysis['crossings'] ?? [], static fn (array $crossing): bool => $closest >= $crossing['start'] - self::CROSSED_MARGIN && $closest <= $crossing['end'] + self::CROSSED_MARGIN);
            $thermals[] = [
                'latitude' => round($group['latitude'], 6),
                'longitude' => round($group['longitude'], 6),
                'bottom' => round($group['bottom']),
                'top' => round($group['top']),
                'climb' => round($climb, 2),
                'pilots' => $group['pilots'],
                'used' => $used,
                'crossed' => $crossed,
                'missed' => !$used && !$crossed && $distance <= self::NEAR && $climb >= self::MIN_CLIMB
                    && $altitude >= $group['bottom'] - self::BELOW_BOTTOM && $altitude <= $group['top'],
                'closest' => ['at' => $closest, 'distance' => round($distance), 'altitude' => round($altitude)],
            ];
        }

        return ['thermals' => $thermals, 'crossings' => $crossings];
    }

    /**
     * @param array<int|string, mixed> $groups
     */
    private static function groupOf(array $groups, float $latitude, float $longitude): ?int
    {
        foreach ($groups as $key => $group) {
            if (self::metres($group['latitude'], $group['longitude'], $latitude, $longitude) < self::SAME_THERMAL) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Average position between two seconds of a series.
     *
     * @param array<string, list<float>> $series
     *
     * @return array{float, float}
     */
    private static function centre(array $series, int $start, int $end): array
    {
        $length = $end - $start + 1;

        return [
            array_sum(\array_slice($series['latitude'], $start, $length)) / $length,
            array_sum(\array_slice($series['longitude'], $start, $length)) / $length,
        ];
    }

    /**
     * The second of the flight closest to a point, and its distance in metres.
     *
     * @param array<string, list<float>> $series
     *
     * @return array{int, float}
     */
    private static function closest(array $series, int $from, int $to, float $latitude, float $longitude): array
    {
        $best = [$from, \INF];
        for ($i = $from; $i <= $to; ++$i) {
            $distance = self::metres($series['latitude'][$i], $series['longitude'][$i], $latitude, $longitude);
            if ($distance < $best[1]) {
                $best = [$i, $distance];
            }
        }

        return $best;
    }

    /** Metres between two close points (flat approximation, faster than Geo::distance()). */
    private static function metres(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return hypot(($lon2 - $lon1) * self::METRES_PER_DEGREE * cos(deg2rad($lat1)), ($lat2 - $lat1) * self::METRES_PER_DEGREE);
    }
}
