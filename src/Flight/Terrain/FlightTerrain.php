<?php

declare(strict_types=1);

namespace App\Flight\Terrain;

use App\Flight\Analysis\FlightAnalysis;

/**
 * Adds the relief to the analysis of a flight (Terrain):
 *
 * - the height of the ground under each second of the track;
 * - the slopes expected to work in the wind of the flight: the wind of the task when the session
 *   of the flight gives it (the base weather zone of Condor), else the average drift of its
 *   thermals (weaker and noisier: 4 km/h on C3-s117 for a task wind of 20 km/h): on a grid of CELL metres within CORRIDOR metres of the track, the faces looking into
 *   the wind (downhill towards where it comes from) and steep enough, where the wind pushed up the
 *   slope would rise at SLOPE_MIN_LIFT m/s or more: wind speed × sin(slope) × cos(angle between
 *   the face and the wind). A model of what Condor does, not what it did: where the glider flew,
 *   the track says what really worked.
 */
final readonly class FlightTerrain
{
    private const int CELL = 200;
    private const float CORRIDOR = 3000.0;
    private const int CORRIDOR_STEP = 10;
    private const float SLOPE_MIN_WIND = 10.0;
    private const float SLOPE_MIN_LIFT = 1.0;
    private const float METRES_PER_DEGREE = 111320.0;

    public function __construct(
        private Terrain $terrain,
    ) {
    }

    /**
     * @param array{from: float, speed: float}|null $wind of the task (degrees, km/h), when known
     */
    public function apply(FlightAnalysis $analysis, ?array $wind = null): FlightAnalysis
    {
        $series = $analysis->series;
        $ground = array_map(fn (float $latitude, float $longitude): ?float => $this->terrain->elevation($latitude, $longitude), $series['latitude'], $series['longitude']);
        if ([] === array_filter($ground, static fn (?float $value): bool => null !== $value)) {
            return $analysis;
        }

        $wind ??= isset($analysis->statistics['windFrom'], $analysis->statistics['windSpeed'])
            ? ['from' => (float) $analysis->statistics['windFrom'], 'speed' => (float) $analysis->statistics['windSpeed']]
            : null;

        return $analysis->withTerrain($ground, null !== $wind ? $this->slopes($series, $wind) : []);
    }

    /**
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param array{from: float, speed: float}                                                                                                     $wind   degrees, km/h
     *
     * @return array{cell?: int, cells?: list<array{float, float, float}>, wind?: array{from: float, speed: float}}
     */
    private function slopes(array $series, array $wind): array
    {
        if ($wind['speed'] < self::SLOPE_MIN_WIND || [] === $series['latitude']) {
            return [];
        }
        $speed = $wind['speed'] / 3.6;
        $from = deg2rad($wind['from']);
        // The grid: CELL metres, from the first point of the track
        $latitude0 = $series['latitude'][0];
        $longitude0 = $series['longitude'][0];
        $perLongitude = self::METRES_PER_DEGREE * cos(deg2rad($latitude0));
        $reach = (int) ceil(self::CORRIDOR / self::CELL);
        $cells = [];
        $count = \count($series['latitude']);
        for ($i = 0; $i < $count; $i += self::CORRIDOR_STEP) {
            $cx = (int) round(($series['longitude'][$i] - $longitude0) * $perLongitude / self::CELL);
            $cy = (int) round(($series['latitude'][$i] - $latitude0) * self::METRES_PER_DEGREE / self::CELL);
            for ($dx = -$reach; $dx <= $reach; ++$dx) {
                for ($dy = -$reach; $dy <= $reach; ++$dy) {
                    if ($dx * $dx + $dy * $dy <= $reach * $reach) {
                        $cells[($cx + $dx).':'.($cy + $dy)] = [$cx + $dx, $cy + $dy];
                    }
                }
            }
        }

        $half = self::CELL / 2;
        $working = [];
        foreach ($cells as [$x, $y]) {
            $latitude = $latitude0 + $y * self::CELL / self::METRES_PER_DEGREE;
            $longitude = $longitude0 + $x * self::CELL / $perLongitude;
            $east = $this->terrain->elevation($latitude, $longitude + $half / $perLongitude);
            $west = $this->terrain->elevation($latitude, $longitude - $half / $perLongitude);
            $north = $this->terrain->elevation($latitude + $half / self::METRES_PER_DEGREE, $longitude);
            $south = $this->terrain->elevation($latitude - $half / self::METRES_PER_DEGREE, $longitude);
            if (null === $east || null === $west || null === $north || null === $south) {
                continue;
            }
            $dzdx = ($east - $west) / self::CELL;
            $dzdy = ($north - $south) / self::CELL;
            $steepness = atan(hypot($dzdx, $dzdy));
            // The way the face looks: downhill
            $facing = atan2(-$dzdx, -$dzdy);
            $lift = $speed * sin($steepness) * cos($facing - $from);
            if ($lift >= self::SLOPE_MIN_LIFT) {
                $working[] = [round($latitude, 5), round($longitude, 5), round($lift, 1)];
            }
        }

        return ['cell' => self::CELL, 'cells' => $working, 'wind' => $wind];
    }
}
