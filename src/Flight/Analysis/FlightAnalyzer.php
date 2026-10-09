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
 * 4. statistics are computed between take-off and landing (ground speed above 40 km/h); 30 s slower
 *    on the way is a stop on the ground (a landing, then a relaunch);
 * 5. with a task, each point is reached when the track enters its zone (a cylinder, or a half
 *    disc for Condor's 180° start and finish), the start being the last exit of the start zone
 *    before the first turnpoint;
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

    /** Seconds under the flying speed, between take-off and landing, that make a stop on the ground. */
    private const int LANDED_TIME = 30;

    /** Metres between the ends of a glide under which it is not a transition (detour). */
    private const float MIN_TRANSITION = 1000.0;

    /** Seconds between the points that measure the distance flown in a glide (detour). */
    private const int DETOUR_STEP = 30;

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
        $phases = self::phases($turnRate, $takeoff, $landing, $altitude, $step, $latitude, $longitude);

        $series = [
            'time' => array_column($points, 0),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'altitude' => $altitude,
            'speed' => $speed,
            'vario' => $vario,
        ];

        $task = \count($track->turnpoints) >= 3 ? self::task($track->turnpoints, $latitude, $longitude) : null;
        $launch = self::launch($series, $track->turnpoints[0] ?? null);
        $statistics = self::statistics($series, $step, $phases, $takeoff, $landing)
            + self::climbing($series, $takeoff, $landing, $launch)
            + self::spirals($turnRate, $altitude, $takeoff, $landing)
            + self::taskFlying($series, $phases, $task);

        return new FlightAnalysis($track, $series, $phases, $statistics, $task, $launch);
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
     * Metres east and north of a point (flat earth: enough for the size of a turnpoint zone).
     *
     * @return array{float, float}
     */
    private static function metres(Turnpoint $origin, float $latitude, float $longitude): array
    {
        return [
            ($longitude - $origin->longitude) * 111320.0 * cos(deg2rad($origin->latitude)),
            ($latitude - $origin->latitude) * 110540.0,
        ];
    }

    /**
     * One point per second, from the first fix to the last (the last point is the last fix).
     *
     * @param list<Fix> $fixes
     *
     * @return list<array{int, float, float, float}> time, latitude, longitude, altitude
     */
    private static function resample(array $fixes): array
    {
        usort($fixes, static fn (Fix $a, Fix $b): int => $a->time <=> $b->time);
        $first = $fixes[0]->time;
        // Up to the second after the last fix: a track cut just after the finish still reaches it
        $duration = (int) ceil(end($fixes)->time - $first);
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
     * @param list<float> $latitude
     * @param list<float> $longitude
     *
     * @return list<Phase>
     */
    private static function phases(array $turnRate, int $takeoff, int $landing, array $altitude, array $step, array $latitude, array $longitude): array
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
                Geo::distance($latitude[$start], $longitude[$start], $latitude[$end], $longitude[$end]),
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
        $stops = 0;
        $slow = 0;
        foreach ($flying('speed') as $value) {
            $slow = $value < self::FLYING_SPEED ? $slow + 1 : 0;
            $stops += self::LANDED_TIME === $slow ? 1 : 0;
        }

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
            'stops' => $stops,
        ];
    }

    /**
     * Height gained from a low point and height climbed in all, from the release (the end of the
     * tow or winch climb, when the glider first sinks for 5 s) to the landing, as Condor Club counts
     * them.
     *
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     *
     * @return array{release: int, bestGain: float, totalClimb: float}
     */
    private static function climbing(array $series, int $takeoff, int $landing, ?Launch $launch): array
    {
        $altitude = $series['altitude'];
        $release = $takeoff;
        if (null !== $launch && Launch::Airborne !== $launch) {
            for ($i = $takeoff + 10; $i + 4 <= $landing; ++$i) {
                if (max(\array_slice($series['vario'], $i, 5)) < 0) {
                    $release = $i;
                    break;
                }
            }
        }
        $low = \INF;
        $bestGain = 0.0;
        $totalClimb = 0.0;
        for ($i = $release; $i <= $landing; ++$i) {
            $low = min($low, $altitude[$i]);
            $bestGain = max($bestGain, $altitude[$i] - $low);
            $totalClimb += $i > $release ? max(0.0, $altitude[$i] - $altitude[$i - 1]) : 0.0;
        }

        return ['release' => $release, 'bestGain' => round($bestGain), 'totalClimb' => round($totalClimb)];
    }

    /**
     * Spirals one way, as Condor Club counts them: each run of at least one full turn without
     * straightening (a thermal often holds several, the glider re-centring between them), with
     * their average climb.
     *
     * @param list<float> $turnRate
     * @param list<float> $altitude
     *
     * @return array{rightSpirals: int, rightClimb: ?float, leftSpirals: int, leftClimb: ?float}
     */
    private static function spirals(array $turnRate, array $altitude, int $takeoff, int $landing): array
    {
        $count = ['right' => 0, 'left' => 0];
        $gain = ['right' => 0.0, 'left' => 0.0];
        $time = ['right' => 0, 'left' => 0];
        $start = null;
        $turn = 0.0;
        for ($i = $takeoff; $i <= $landing + 1; ++$i) {
            $rate = $i <= $landing ? $turnRate[$i] : 0.0;
            $circling = abs($rate) >= self::CIRCLING_RATE && (null === $start || ($rate > 0) === ($turn > 0));
            if ($circling) {
                $start ??= $i;
                $turn += $rate;
                continue;
            }
            if (null !== $start && abs($turn) >= 360.0) {
                $direction = $turn > 0 ? 'right' : 'left';
                ++$count[$direction];
                $gain[$direction] += $altitude[$i - 1] - $altitude[$start];
                $time[$direction] += $i - 1 - $start;
            }
            $start = abs($rate) >= self::CIRCLING_RATE ? $i : null;
            $turn = null !== $start ? $rate : 0.0;
        }

        return [
            'rightSpirals' => $count['right'],
            'rightClimb' => $time['right'] > 0 ? round($gain['right'] / $time['right'], 2) : null,
            'leftSpirals' => $count['left'],
            'leftClimb' => $time['left'] > 0 ? round($gain['left'] / $time['left'], 2) : null,
        ];
    }

    /**
     * On the task when it was finished, otherwise on the whole flight: the band of the thermals
     * (average altitude at their entry and exit) and the detour of the glides (distance flown over
     * the straight lines between their ends, split at the turnpoints reached on the way).
     *
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param list<Phase>                                                                                                                          $phases
     *
     * @return array{bandBottom: ?float, bandTop: ?float, glideDetour: ?float}
     */
    private static function taskFlying(array $series, array $phases, ?TaskResult $task): array
    {
        $from = null !== $task && $task->isCompleted() ? (int) $task->start() : 0;
        $to = null !== $task && $task->isCompleted() ? (int) $task->finish() : \PHP_INT_MAX;
        $reached = null !== $task ? array_filter($task->reached, static fn (?int $index): bool => null !== $index) : [];
        $thermals = [];
        $flown = 0.0;
        $straight = 0.0;
        foreach ($phases as $phase) {
            if ($phase->start < $from || $phase->end > $to) {
                continue;
            }
            if (Phase::THERMAL === $phase->type) {
                $thermals[] = $phase;
                continue;
            }
            // A glide that does not lose height, or of less than a kilometre, is not a transition
            if ($phase->gain >= 0 || $phase->straight < self::MIN_TRANSITION) {
                continue;
            }
            $cuts = array_values(array_filter($reached, static fn (int $index): bool => $index > $phase->start && $index < $phase->end));
            $points = [$phase->start, ...$cuts, $phase->end];
            $at = static fn (int $a, int $b): float => Geo::distance($series['latitude'][$a], $series['longitude'][$a], $series['latitude'][$b], $series['longitude'][$b]);
            for ($k = 1; $k < \count($points); ++$k) {
                $straight += $at($points[$k - 1], $points[$k]);
                // Flown distance from a point every DETOUR_STEP seconds, as Condor Club measures it
                // (the wobbles of the 1 s track would count as a detour)
                for ($i = $points[$k - 1]; $i < $points[$k]; $i += self::DETOUR_STEP) {
                    $flown += $at($i, min($i + self::DETOUR_STEP, $points[$k]));
                }
            }
        }
        $average = static fn (string $end): ?float => [] !== $thermals
            ? round(array_sum(array_map(static fn (Phase $phase): float => $series['altitude']['start' === $end ? $phase->start : $phase->end], $thermals)) / \count($thermals))
            : null;

        return [
            'bandBottom' => $average('start'),
            'bandTop' => $average('end'),
            'glideDetour' => $straight > 0 ? round($flown / $straight - 1, 3) : null,
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
        $last = \count($turnpoints) - 1;
        // Zone of a point: a cylinder, or for 180° a half disc on the side of the leg the glider comes
        // from (finish) or goes to the start from (start), i.e. behind the line across the leg
        $inside = static function (int $i, Turnpoint $point, ?Turnpoint $from = null, ?Turnpoint $to = null) use ($latitude, $longitude): bool {
            if (Geo::distance($latitude[$i], $longitude[$i], $point->latitude, $point->longitude) > $point->radius) {
                return false;
            }
            if (!$point->isLine() || null === $from || null === $to) {
                return true;
            }
            [$x, $y] = self::metres($point, $latitude[$i], $longitude[$i]);
            [$ux, $uy] = self::metres($from, $to->latitude, $to->longitude);

            return $x * $ux + $y * $uy <= 0;
        };

        $reached = array_fill(0, \count($turnpoints), null);
        $from = 0;
        for ($k = 2; $k <= $last; ++$k) {
            $point = $turnpoints[$k];
            for ($i = $from; $i < $count && !$inside($i, $point, $turnpoints[$k - 1], $point); ++$i) {
            }
            if ($i >= $count) {
                break;
            }
            $reached[$k] = $i;
            $from = $i;
        }
        // Start: the last exit of the start zone before the first turnpoint (or the end of the track)
        $next = $turnpoints[2] ?? null;
        for ($s = $reached[2] ?? $count - 1; $s >= 0 && !$inside($s, $turnpoints[1], $turnpoints[1], $next); --$s) {
        }
        $reached[1] = $s >= 0 ? min($s + 1, $count - 1) : null;

        $distance = 0.0;
        for ($k = 1; $k < \count($turnpoints) - 1; ++$k) {
            $distance += Geo::distance($turnpoints[$k]->latitude, $turnpoints[$k]->longitude, $turnpoints[$k + 1]->latitude, $turnpoints[$k + 1]->longitude);
        }

        return new TaskResult($turnpoints, array_values($reached), $distance);
    }
}
