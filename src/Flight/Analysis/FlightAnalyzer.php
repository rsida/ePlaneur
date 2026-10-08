<?php

declare(strict_types=1);

namespace App\Flight\Analysis;

use App\Flight\Fix;
use App\Flight\Geo\Geo;
use App\Flight\Track;
use App\Flight\Turnpoint;

/**
 * Computes what the flight viewer shows from a track:
 *
 * 1. the track is resampled to one point per second (linear interpolation), so every series shares
 *    the same time axis whatever the recording rate (about 10 per second in Condor, 1 in IGC);
 * 2. ground speed and vario are measured over 4 seconds centred on each point, which removes the
 *    noise of differences between close points;
 * 3. circling is detected from the turn rate (heading change over 10 seconds): circling segments
 *    are thermals, the rest glides; short pieces are merged into their neighbours (a thermal being
 *    re-centred stays one thermal);
 * 4. statistics are computed between take-off and landing (ground speed above 40 km/h);
 * 5. with a task, each point is reached when the track enters its cylinder, the start being the
 *    last exit of the start cylinder;
 * 6. the launch is read from the beginning of the track: airborne when it does not begin on the
 *    ground, winch when the glider climbs fast after lift-off, aerotow otherwise.
 */
final class FlightAnalyzer
{
    /** Half width of the window for speed and vario, in seconds (4 s centred). */
    private const int MEASURE_HALF_WINDOW = 2;

    /** Half width of the window averaging the turn rate, in seconds. */
    private const int TURN_HALF_WINDOW = 5;

    /** Degrees per second above which the glider is circling (a 360° in one minute). */
    private const float CIRCLING_RATE = 6.0;

    /** Shortest thermal and shortest glide between two thermals, in seconds. */
    private const int MIN_PHASE = 20;

    /** Ground speed above which the glider is flying, km/h. */
    private const float FLYING_SPEED = 40.0;

    /** Height lost under which a glide has no glide ratio, metres. */
    private const float MIN_GLIDE_LOSS = 10.0;

    /** Seconds the glider stays still at the beginning of a track that begins on the ground. */
    private const int GROUND_STILL = 5;

    /** Height above the departure airfield under which the track begins on the ground, metres. */
    private const float GROUND_HEIGHT = 50.0;

    /** Height above the ground where the glider has lifted off, metres. */
    private const float LIFT_OFF_HEIGHT = 10.0;

    /**
     * Height gained in the 30 s after lift-off beyond which the launch is a winch (10 to 20 m/s);
     * behind a tug the glider climbs at 2 to 5 m/s.
     */
    private const float WINCH_GAIN = 150.0;

    public function analyze(Track $track): FlightAnalysis
    {
        $points = self::resample($track->fixes);
        $count = \count($points);
        $latitude = array_column($points, 1);
        $longitude = array_column($points, 2);
        $altitude = array_column($points, 3);

        $speed = [];
        $vario = [];
        $heading = [];
        $step = [];
        for ($i = 0; $i < $count; ++$i) {
            $a = max(0, $i - self::MEASURE_HALF_WINDOW);
            $b = min($count - 1, $i + self::MEASURE_HALF_WINDOW);
            $distance = Geo::distance($latitude[$a], $longitude[$a], $latitude[$b], $longitude[$b]);
            $speed[] = $b > $a ? $distance / ($b - $a) * 3.6 : 0.0;
            $vario[] = $b > $a ? ($altitude[$b] - $altitude[$a]) / ($b - $a) : 0.0;
            $heading[] = $distance > 1.0 ? Geo::bearing($latitude[$a], $longitude[$a], $latitude[$b], $longitude[$b]) : ($heading[$i - 1] ?? 0.0);
            $step[] = $i > 0 ? Geo::distance($latitude[$i - 1], $longitude[$i - 1], $latitude[$i], $longitude[$i]) : 0.0;
        }

        [$takeoff, $landing] = self::flyingRange($speed);
        $turnRate = self::turnRates($heading);
        $phases = self::phases($turnRate, $takeoff, $landing, $altitude, $step);

        $series = [
            'time' => array_column($points, 0),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'altitude' => $altitude,
            'speed' => $speed,
            'vario' => $vario,
        ];

        return new FlightAnalysis(
            $track,
            $series,
            $phases,
            self::statistics($series, $step, $phases, $takeoff, $landing),
            \count($track->turnpoints) >= 3 ? self::task($track->turnpoints, $latitude, $longitude) : null,
            self::launch($series, $track->turnpoints[0] ?? null),
        );
    }

    /**
     * Airborne when the track does not begin on the ground: still for a few seconds, or at the
     * height of the departure airfield (the first point of a Condor task). From the ground, winch or
     * aerotow from the height gained in the 30 s after lift-off; null when the glider never lifts off.
     *
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     */
    private static function launch(array $series, ?Turnpoint $departure): ?Launch
    {
        $altitude = $series['altitude'];
        $count = \count($altitude);
        $still = $count > self::GROUND_STILL;
        for ($i = 0; $still && $i < self::GROUND_STILL; ++$i) {
            $still = $series['speed'][$i] < 15.0 && abs($altitude[$i] - $altitude[0]) < 1.0;
        }
        $atAirfield = null !== $departure
            && abs($altitude[0] - $departure->altitude) < self::GROUND_HEIGHT
            && Geo::distance($series['latitude'][0], $series['longitude'][0], $departure->latitude, $departure->longitude) < 2000.0;
        if (!$still && !$atAirfield) {
            return Launch::Airborne;
        }

        for ($i = 0; $i < $count; ++$i) {
            if ($altitude[$i] > $altitude[0] + self::LIFT_OFF_HEIGHT) {
                $gain = $altitude[min($i + 30, $count - 1)] - $altitude[$i];

                return $gain >= self::WINCH_GAIN ? Launch::Winch : Launch::Aerotow;
            }
        }

        return null;
    }

    /**
     * One point per second, from the first fix to the last.
     *
     * @param list<Fix> $fixes
     *
     * @return list<array{int, float, float, float}> time, latitude, longitude, altitude
     */
    private static function resample(array $fixes): array
    {
        usort($fixes, static fn (Fix $a, Fix $b): int => $a->time <=> $b->time);
        $first = $fixes[0]->time;
        $duration = (int) floor(end($fixes)->time - $first);
        $points = [];
        $j = 0;
        $last = \count($fixes) - 1;
        for ($t = 0; $t <= $duration; ++$t) {
            $time = $first + $t;
            while ($j < $last - 1 && $fixes[$j + 1]->time <= $time) {
                ++$j;
            }
            $a = $fixes[$j];
            $b = $fixes[min($j + 1, $last)];
            $ratio = $b->time > $a->time ? min(1.0, max(0.0, ($time - $a->time) / ($b->time - $a->time))) : 0.0;
            $points[] = [
                $t,
                $a->latitude + ($b->latitude - $a->latitude) * $ratio,
                $a->longitude + ($b->longitude - $a->longitude) * $ratio,
                $a->altitude + ($b->altitude - $a->altitude) * $ratio,
            ];
        }

        return $points;
    }

    /**
     * First and last points where the glider flies; the whole track when it never slows down.
     *
     * @param list<float> $speed
     *
     * @return array{int, int}
     */
    private static function flyingRange(array $speed): array
    {
        $flying = array_keys(array_filter($speed, static fn (float $value): bool => $value >= self::FLYING_SPEED));

        return [] === $flying ? [0, \count($speed) - 1] : [$flying[0], $flying[\count($flying) - 1]];
    }

    /**
     * Turn rate in degrees per second (positive: to the right), averaged over 11 seconds.
     *
     * @param list<float> $heading
     *
     * @return list<float>
     */
    private static function turnRates(array $heading): array
    {
        $count = \count($heading);
        $raw = [];
        for ($i = 0; $i < $count; ++$i) {
            $a = max(0, $i - 1);
            $b = min($count - 1, $i + 1);
            $raw[] = $b > $a ? Geo::turn($heading[$a], $heading[$b]) / ($b - $a) : 0.0;
        }

        $rates = [];
        $sum = 0.0;
        $window = [];
        // Centred moving average
        for ($i = -self::TURN_HALF_WINDOW; $i < $count; ++$i) {
            $next = $i + self::TURN_HALF_WINDOW;
            if ($next < $count) {
                $window[] = $raw[$next];
                $sum += $raw[$next];
            }
            if (\count($window) > 2 * self::TURN_HALF_WINDOW + 1) {
                $sum -= array_shift($window);
            }
            if ($i >= 0) {
                $rates[] = $sum / \count($window);
            }
        }

        return $rates;
    }

    /**
     * @param list<float> $turnRate
     * @param list<float> $altitude
     * @param list<float> $step
     *
     * @return list<Phase>
     */
    private static function phases(array $turnRate, int $takeoff, int $landing, array $altitude, array $step): array
    {
        // Runs of circling / straight flight: [circling, start, end]
        $runs = [];
        for ($i = $takeoff; $i <= $landing; ++$i) {
            $circling = abs($turnRate[$i]) >= self::CIRCLING_RATE;
            if ([] !== $runs && $runs[\count($runs) - 1][0] === $circling) {
                $runs[\count($runs) - 1][2] = $i;
            } else {
                $runs[] = [$circling, $i, $i];
            }
        }
        // A short circling is a turn, not a thermal; then a short straight part between two
        // thermals is a re-centring, not a glide
        $runs = self::merge(array_map(static fn (array $run): array => [$run[0] && $run[2] - $run[1] >= self::MIN_PHASE, $run[1], $run[2]], $runs));
        $last = \count($runs) - 1;
        $runs = self::merge(array_map(static fn (array $run, int $index): array => [
            $run[0] || ($index > 0 && $index < $last && $run[2] - $run[1] < self::MIN_PHASE),
            $run[1],
            $run[2],
        ], $runs, array_keys($runs)));

        $phases = [];
        foreach ($runs as [$circling, $start, $end]) {
            $gain = $altitude[$end] - $altitude[$start];
            $distance = array_sum(\array_slice($step, $start + 1, $end - $start));
            $turn = array_sum(\array_slice($turnRate, $start, $end - $start + 1));
            $phases[] = new Phase(
                $circling ? Phase::THERMAL : Phase::GLIDE,
                $start,
                $end,
                $gain,
                $distance,
                !$circling && -$gain >= self::MIN_GLIDE_LOSS ? $distance / -$gain : null,
                $circling ? ($turn >= 0 ? 'right' : 'left') : null,
            );
        }

        return $phases;
    }

    /**
     * Joins consecutive runs of the same kind.
     *
     * @param list<array{bool, int, int}> $runs
     *
     * @return list<array{bool, int, int}>
     */
    private static function merge(array $runs): array
    {
        $merged = [];
        foreach ($runs as $run) {
            if ([] !== $merged && $merged[\count($merged) - 1][0] === $run[0]) {
                $merged[\count($merged) - 1][2] = $run[2];
            } else {
                $merged[] = $run;
            }
        }

        return $merged;
    }

    /**
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param list<float>                                                                                                                          $step
     * @param list<Phase>                                                                                                                          $phases
     *
     * @return array<string, int|float|null>
     */
    private static function statistics(array $series, array $step, array $phases, int $takeoff, int $landing): array
    {
        $flying = static fn (string $name): array => \array_slice($series[$name], $takeoff, $landing - $takeoff + 1);
        $duration = $landing - $takeoff;
        $distance = array_sum(\array_slice($step, $takeoff + 1, $duration));
        $thermals = array_values(array_filter($phases, static fn (Phase $phase): bool => Phase::THERMAL === $phase->type));
        $glides = array_values(array_filter($phases, static fn (Phase $phase): bool => Phase::GLIDE === $phase->type));
        $sum = static fn (array $phases, callable $value): float => array_sum(array_map($value, $phases));

        $thermalTime = $sum($thermals, static fn (Phase $phase): int => $phase->duration());
        $thermalGain = $sum($thermals, static fn (Phase $phase): float => $phase->gain);
        $leftTime = $sum(array_filter($thermals, static fn (Phase $phase): bool => 'left' === $phase->direction), static fn (Phase $phase): int => $phase->duration());
        $glideTime = $sum($glides, static fn (Phase $phase): int => $phase->duration());
        $glideDistance = $sum($glides, static fn (Phase $phase): float => $phase->distance);
        $glideLoss = $sum($glides, static fn (Phase $phase): float => max(0.0, -$phase->gain));
        $round = static fn (float $value, int $precision = 0): float => round($value, $precision);

        return [
            'takeoff' => $takeoff,
            'landing' => $landing,
            'duration' => $duration,
            'distance' => $round($distance),
            'altitudeMax' => $round(max($flying('altitude'))),
            'altitudeMin' => $round(min($flying('altitude'))),
            'speedMax' => $round(max($flying('speed')), 1),
            'speedAverage' => $duration > 0 ? $round($distance / $duration * 3.6, 1) : null,
            'varioMax' => $round(max($flying('vario')), 1),
            'varioMin' => $round(min($flying('vario')), 1),
            'thermals' => \count($thermals),
            'thermalTime' => (int) $thermalTime,
            'thermalGain' => $round($thermalGain),
            'climbAverage' => $thermalTime > 0 ? $round($thermalGain / $thermalTime, 2) : null,
            'circlingShare' => $duration > 0 ? $round($thermalTime / $duration, 3) : null,
            'leftShare' => $thermalTime > 0 ? $round($leftTime / $thermalTime, 3) : null,
            'glideTime' => (int) $glideTime,
            'glideDistance' => $round($glideDistance),
            'glideRatio' => $glideLoss >= self::MIN_GLIDE_LOSS ? $round($glideDistance / $glideLoss, 1) : null,
            'glideSpeed' => $glideTime > 0 ? $round($glideDistance / $glideTime * 3.6, 1) : null,
        ];
    }

    /**
     * @param list<Turnpoint> $turnpoints
     * @param list<float>     $latitude
     * @param list<float>     $longitude
     */
    private static function task(array $turnpoints, array $latitude, array $longitude): TaskResult
    {
        $count = \count($latitude);
        $inside = static fn (int $i, Turnpoint $point): bool => Geo::distance($latitude[$i], $longitude[$i], $point->latitude, $point->longitude) <= $point->radius;

        $reached = array_fill(0, \count($turnpoints), null);
        $from = 0;
        for ($k = 2; $k < \count($turnpoints); ++$k) {
            for ($i = $from; $i < $count && !$inside($i, $turnpoints[$k]); ++$i) {
            }
            if ($i >= $count) {
                break;
            }
            $reached[$k] = $i;
            $from = $i;
        }
        // Start: last exit of the start cylinder before the first turnpoint (or the end of the track)
        for ($s = $reached[2] ?? $count - 1; $s >= 0 && !$inside($s, $turnpoints[1]); --$s) {
        }
        $reached[1] = $s >= 0 ? $s : null;

        $distance = 0.0;
        for ($k = 1; $k < \count($turnpoints) - 1; ++$k) {
            $distance += Geo::distance($turnpoints[$k]->latitude, $turnpoints[$k]->longitude, $turnpoints[$k + 1]->latitude, $turnpoints[$k + 1]->longitude);
        }

        return new TaskResult($turnpoints, array_values($reached), $distance);
    }
}
