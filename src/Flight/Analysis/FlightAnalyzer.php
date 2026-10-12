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
 *    re-centred stays one thermal); a glide that climbs (1 m/s for a minute, 50 m) is slope or
 *    wave lift; thermals get their turns, radius, bank and wind (drift of the circles);
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

    /** Straight flight climbing at least LIFT_CLIMB m/s (over LIFT_WINDOW s) for LIFT_TIME s and LIFT_GAIN m is slope or wave lift. */
    private const int LIFT_WINDOW = 30;
    private const int LIFT_TIME = 60;
    private const float LIFT_GAIN = 50.0;
    private const float LIFT_CLIMB = 1.0;

    /** Seconds under the flying speed, between take-off and landing, that make a stop on the ground. */
    private const int LANDED_TIME = 30;

    /** Metres between the ends of a glide under which it is not a transition (detour). */
    private const float MIN_TRANSITION = 1000.0;

    /** Seconds between the points that measure the distance flown in a glide (detour). */
    private const int DETOUR_STEP = 30;

    /** Height lost under which a glide has no glide ratio, metres. */
    private const float MIN_GLIDE_LOSS = 10.0;

    /** Centring radar of a thermal: two full turns at least, and seconds in an eighth of the circle to give it a value. */
    private const float CENTRING_MIN_TURNS = 2.0;
    private const int CENTRING_MIN_SECONDS = 3;

    /** A thermal is centred from its first circle climbing at least this share of its best circle. */
    private const float CENTRED_SHARE = 0.7;

    /**
     * Lift crossed in a glide: the total energy vario over CROSSING_WINDOW seconds (height plus the
     * height the speed is worth, so that a pull-up is not lift) at least CROSSING_CLIMB m/s for
     * CROSSING_TIME seconds, away from the thermals around the glide (the turn-in and the
     * acceleration after the exit).
     */
    private const int CROSSING_WINDOW = 10;
    private const float CROSSING_CLIMB = 1.0;
    private const int CROSSING_TIME = 5;
    private const int CROSSING_MARGIN_AFTER = 20;
    private const int CROSSING_MARGIN_BEFORE = 30;

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
        // Climbs, vario and phases on the altitude without the height recoveries of Condor (the
        // glider put higher at once); the altitude itself (highest, band, finish, ground) stays real
        $climb = [] !== $track->recoveries ? array_column(self::resample(self::withoutRecoveries($track)), 3) : $altitude;

        $speed = [];
        $vario = [];
        $heading = [];
        $step = [];
        for ($i = 0; $i < $count; ++$i) {
            $a = max(0, $i - self::MEASURE_HALF_WINDOW);
            $b = min($count - 1, $i + self::MEASURE_HALF_WINDOW);
            $distance = Geo::distance($latitude[$a], $longitude[$a], $latitude[$b], $longitude[$b]);
            $speed[] = $b > $a ? $distance / ($b - $a) * 3.6 : 0.0;
            $vario[] = $b > $a ? ($climb[$b] - $climb[$a]) / ($b - $a) : 0.0;
            $heading[] = $distance > 1.0 ? Geo::bearing($latitude[$a], $longitude[$a], $latitude[$b], $longitude[$b]) : ($heading[$i - 1] ?? 0.0);
            $step[] = $i > 0 ? Geo::distance($latitude[$i - 1], $longitude[$i - 1], $latitude[$i], $longitude[$i]) : 0.0;
        }

        [$takeoff, $landing] = self::flyingRange($speed);
        $turnRate = self::turnRates($heading);
        $phases = self::phases($turnRate, $takeoff, $landing, $climb, $step, $latitude, $longitude);
        $phases = self::describe(self::splitLift($phases, $climb, $step, $latitude, $longitude), $turnRate, $speed, $vario, $latitude, $longitude);

        $series = [
            'time' => array_column($points, 0),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'altitude' => $altitude,
            'speed' => $speed,
            'vario' => $vario,
        ];
        $climbSeries = ['altitude' => $climb] + $series;

        $task = \count($track->turnpoints) >= 3 ? self::task($track->turnpoints, $latitude, $longitude) : null;
        $launch = self::launch($series, $track->turnpoints[0] ?? null);
        $climbing = self::climbing($climbSeries, $takeoff, $landing, $launch);
        // Climbing straight behind the tug or on the cable is not slope or wave lift
        $phases = array_map(static fn (Phase $phase): Phase => Phase::LIFT === $phase->type && $phase->start < $climbing['release'] ? $phase->as(Phase::LAUNCH) : $phase, $phases);
        $statistics = self::statistics($series, $step, $phases, $takeoff, $landing)
            + $climbing
            + self::spirals($turnRate, $climb, $takeoff, $landing)
            + self::taskFlying($climbSeries, $phases, $task, $altitude)
            + self::circling($climbSeries, $phases, $task);
        $phases = self::coach($phases, $climb, $statistics['climbAverage']);

        // Bank and pitch recorded by Condor 3 (.ftr), when every second has them
        $bank = array_column($points, 4);
        $pitch = array_column($points, 5);
        $attitude = !\in_array(null, $bank, true) && !\in_array(null, $pitch, true) ? ['bank' => $bank, 'pitch' => $pitch] : null;

        return new FlightAnalysis($track, $series, $phases, $statistics, $task, $launch, self::crossings($climbSeries, $phases), attitude: $attitude, climb: [] !== $track->recoveries ? $climb : null);
    }

    /**
     * The fixes with the height of the recoveries taken off from each one onwards: a continuous
     * altitude, for the climbs.
     *
     * @return list<Fix>
     */
    private static function withoutRecoveries(Track $track): array
    {
        return array_map(static function (Fix $fix) use ($track): Fix {
            $lift = 0.0;
            foreach ($track->recoveries as $recovery) {
                $lift += $fix->time >= $recovery['time'] ? $recovery['height'] : 0.0;
            }

            return new Fix($fix->time, $fix->latitude, $fix->longitude, $fix->altitude - $lift, $fix->bank, $fix->pitch);
        }, $track->fixes);
    }

    /**
     * Airborne when the track does not begin on the ground: still for a few seconds, or at the
     * height of the departure airfield (the first point of a Condor task). Condor 3 also holds an
     * airborne start still for a few seconds: high above the departure airfield, still is not on
     * the ground. From the ground, winch or aerotow from the height gained in the 30 s after
     * lift-off; null when the glider never lifts off.
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
        $aboveAirfield = null !== $departure && $departure->altitude > 0.0 && $altitude[0] > $departure->altitude + self::GROUND_HEIGHT;
        if ($aboveAirfield || (!$still && !$atAirfield)) {
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
     * @return list<array{int, float, float, float, ?float, ?float}> time, latitude, longitude, altitude, bank, pitch
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
            $lerp = static fn (?float $from, ?float $to): ?float => null !== $from && null !== $to ? $from + ($to - $from) * $ratio : null;
            $points[] = [
                $t,
                $a->latitude + ($b->latitude - $a->latitude) * $ratio,
                $a->longitude + ($b->longitude - $a->longitude) * $ratio,
                $a->altitude + ($b->altitude - $a->altitude) * $ratio,
                $lerp($a->bank, $b->bank),
                $lerp($a->pitch, $b->pitch),
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
     * Cuts out of the glides the parts flown straight while climbing (slope or wave lift): the
     * altitude gained over LIFT_WINDOW seconds stays above LIFT_CLIMB m/s for LIFT_TIME seconds and
     * LIFT_GAIN metres.
     *
     * @param list<Phase> $phases
     * @param list<float> $altitude
     * @param list<float> $step
     * @param list<float> $latitude
     * @param list<float> $longitude
     *
     * @return list<Phase>
     */
    private static function splitLift(array $phases, array $altitude, array $step, array $latitude, array $longitude): array
    {
        $count = \count($altitude);
        $half = intdiv(self::LIFT_WINDOW, 2);
        $make = static function (string $type, int $start, int $end) use ($altitude, $step, $latitude, $longitude): Phase {
            $gain = $altitude[$end] - $altitude[$start];
            $distance = array_sum(\array_slice($step, $start + 1, $end - $start));

            return new Phase($type, $start, $end, $gain, $distance, Phase::GLIDE === $type && -$gain >= self::MIN_GLIDE_LOSS ? $distance / -$gain : null, null, Geo::distance($latitude[$start], $longitude[$start], $latitude[$end], $longitude[$end]));
        };
        $result = [];
        foreach ($phases as $phase) {
            if (Phase::GLIDE !== $phase->type) {
                $result[] = $phase;
                continue;
            }
            // Runs of climbing straight flight inside the glide
            $runs = [];
            $runStart = null;
            for ($i = $phase->start; $i <= $phase->end + 1; ++$i) {
                $climbing = $i <= $phase->end
                    && ($altitude[min($count - 1, $i + $half)] - $altitude[max(0, $i - $half)]) / self::LIFT_WINDOW >= self::LIFT_CLIMB;
                if ($climbing) {
                    $runStart ??= $i;
                } elseif (null !== $runStart) {
                    $end = $i - 1;
                    if ($end - $runStart >= self::LIFT_TIME && $altitude[$end] - $altitude[$runStart] >= self::LIFT_GAIN) {
                        $runs[] = [$runStart, $end];
                    }
                    $runStart = null;
                }
            }
            if ([] === $runs) {
                $result[] = $phase;
                continue;
            }
            // Pieces of glide shorter than MIN_PHASE at the ends of the glide join the lift next to them
            if ($runs[0][0] - $phase->start < self::MIN_PHASE) {
                $runs[0][0] = $phase->start;
            }
            $last = \count($runs) - 1;
            if ($phase->end - $runs[$last][1] < self::MIN_PHASE) {
                $runs[$last][1] = $phase->end;
            }
            $cursor = $phase->start;
            foreach ($runs as [$start, $end]) {
                if ($start > $cursor) {
                    $result[] = $make(Phase::GLIDE, $cursor, $start);
                }
                $result[] = $make(Phase::LIFT, $start, $end);
                $cursor = $end;
            }
            $result[] = $make(Phase::GLIDE, $cursor, $phase->end);
        }

        // A glide left with no length after a lift ends the flight: drop it
        return array_values(array_filter($result, static fn (Phase $phase): bool => $phase->end > $phase->start));
    }

    /**
     * Thermals get their turns, radius, bank, wind and centring.
     *
     * @param list<Phase> $phases
     * @param list<float> $turnRate
     * @param list<float> $speed
     * @param list<float> $vario
     * @param list<float> $latitude
     * @param list<float> $longitude
     *
     * @return list<Phase>
     */
    private static function describe(array $phases, array $turnRate, array $speed, array $vario, array $latitude, array $longitude): array
    {
        return array_map(static function (Phase $phase) use ($turnRate, $speed, $vario, $latitude, $longitude): Phase {
            $duration = $phase->duration();
            if (Phase::THERMAL !== $phase->type || $duration <= 0) {
                return $phase;
            }
            $rates = \array_slice($turnRate, $phase->start, $duration + 1);
            $turned = abs(array_sum($rates));
            $omega = deg2rad(array_sum(array_map('abs', $rates)) / \count($rates));
            $velocity = array_sum(\array_slice($speed, $phase->start, $duration + 1)) / ($duration + 1) / 3.6;
            $radius = $omega > 0 ? $velocity / $omega : null;
            $bank = $omega > 0 ? rad2deg(atan($velocity * $omega / 9.81)) : null;
            // Wind: drift from the centre of the first circle to the centre of the last one
            $circle = $omega > 0 ? min($duration, (int) round(2 * \M_PI / $omega)) : $duration;
            $windSpeed = null;
            $windFrom = null;
            if ($circle > 0 && $duration > $circle) {
                $centre = static function (int $from) use ($circle, $latitude, $longitude): array {
                    return [
                        array_sum(\array_slice($latitude, $from, $circle)) / $circle,
                        array_sum(\array_slice($longitude, $from, $circle)) / $circle,
                    ];
                };
                [$lat1, $lon1] = $centre($phase->start);
                [$lat2, $lon2] = $centre($phase->end - $circle);
                $drift = Geo::distance($lat1, $lon1, $lat2, $lon2);
                $windSpeed = round($drift / ($duration - $circle) * 3.6, 1);
                $windFrom = $drift > 1.0 ? round(fmod(Geo::bearing($lat1, $lon1, $lat2, $lon2) + 180.0, 360.0)) : null;
            }

            $centring = $turned >= 360.0 * self::CENTRING_MIN_TURNS ? self::centring($phase, $circle, $vario, $latitude, $longitude) : null;

            return $phase->withCircles(round($turned / 360.0, 1), null !== $radius ? round($radius) : null, null !== $bank ? round($bank) : null, $windSpeed, $windFrom, $centring);
        }, $phases);
    }

    /**
     * Where the thermal climbed best around the circles: the average vario in each eighth of the
     * circle, from north clockwise, by the bearing of the glider from the centre of its circle (the
     * average position over the circle around that second, which follows the drift). An eighth
     * crossed for less than CENTRING_MIN_SECONDS says nothing (null).
     *
     * @param list<float> $vario
     * @param list<float> $latitude
     * @param list<float> $longitude
     *
     * @return list<?float>
     */
    private static function centring(Phase $phase, int $circle, array $vario, array $latitude, array $longitude): array
    {
        $half = intdiv($circle, 2);
        $sums = array_fill(0, 8, 0.0);
        $counts = array_fill(0, 8, 0);
        for ($i = $phase->start + $half; $i <= $phase->end - $half; ++$i) {
            $from = $i - $half;
            $length = 2 * $half + 1;
            $lat = array_sum(\array_slice($latitude, $from, $length)) / $length;
            $lon = array_sum(\array_slice($longitude, $from, $length)) / $length;
            $sector = ((int) floor((Geo::bearing($lat, $lon, $latitude[$i], $longitude[$i]) + 22.5) / 45.0)) % 8;
            $sums[$sector] += $vario[$i];
            ++$counts[$sector];
        }

        return array_map(static fn (float $sum, int $count): ?float => $count >= self::CENTRING_MIN_SECONDS ? round($sum / $count, 2) : null, $sums, $counts);
    }

    /**
     * Entry and exit of the thermals of two turns or more, from the climb over each circle: the
     * seconds before the first circle that climbs CENTRED_SHARE of the best one (centring), and the
     * seconds after the last circle that climbs the average climb of the flight (`$average`), when
     * the thermal reached it (staying while the thermal weakens, against MacCready).
     *
     * @param list<Phase> $phases
     * @param list<float> $altitude
     *
     * @return list<Phase>
     */
    private static function coach(array $phases, array $altitude, int|float|null $average): array
    {
        return array_map(static function (Phase $phase) use ($altitude, $average): Phase {
            if (Phase::THERMAL !== $phase->type || null === $phase->turns || $phase->turns < self::CENTRING_MIN_TURNS) {
                return $phase;
            }
            $circle = (int) round($phase->duration() / $phase->turns);
            $climbs = [];
            for ($i = $phase->start + $circle; $i <= $phase->end; ++$i) {
                $climbs[$i] = ($altitude[$i] - $altitude[$i - $circle]) / $circle;
            }
            $best = [] !== $climbs ? max($climbs) : 0.0;
            if ($circle < 1 || $best <= 0.0) {
                return $phase;
            }
            $entry = null;
            foreach ($climbs as $i => $climb) {
                if ($climb >= self::CENTRED_SHARE * $best) {
                    $entry = $i - $circle - $phase->start;
                    break;
                }
            }
            $exit = null;
            if (null !== $average && $average > 0 && $best >= $average) {
                foreach (array_reverse($climbs, true) as $i => $climb) {
                    if ($climb >= $average) {
                        $exit = $phase->end - $i;
                        break;
                    }
                }
            }

            return $phase->withCoaching($entry, $exit);
        }, $phases);
    }

    /**
     * Lift crossed in straight flight during the glides (dolphin flight, or lift not used), with
     * the best total energy vario over CROSSING_WINDOW seconds in it (m/s) and the height gained
     * across it (metres).
     *
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param list<Phase>                                                                                                                          $phases
     *
     * @return list<array{start: int, end: int, vario: float, gain: float}>
     */
    private static function crossings(array $series, array $phases): array
    {
        $altitude = $series['altitude'];
        $speed = $series['speed'];
        $energy = static fn (int $i): float => $altitude[$i] + ($speed[$i] / 3.6) ** 2 / (2 * 9.81);
        $crossings = [];
        foreach ($phases as $phase) {
            if (Phase::GLIDE !== $phase->type) {
                continue;
            }
            $run = null;
            $last = $phase->end - self::CROSSING_MARGIN_BEFORE - self::CROSSING_WINDOW;
            for ($i = $phase->start + self::CROSSING_MARGIN_AFTER; $i <= $last + 1; ++$i) {
                $climb = $i <= $last ? ($energy($i + self::CROSSING_WINDOW) - $energy($i)) / self::CROSSING_WINDOW : null;
                if (null !== $climb && $climb >= self::CROSSING_CLIMB) {
                    $run ??= ['start' => $i, 'end' => $i, 'vario' => $climb];
                    $run['end'] = $i;
                    $run['vario'] = max($run['vario'], $climb);
                    continue;
                }
                if (null !== $run && $run['end'] - $run['start'] + 1 >= self::CROSSING_TIME) {
                    $end = $run['end'] + self::CROSSING_WINDOW;
                    $previous = array_key_last($crossings);
                    // Two runs whose windows overlap are one lift
                    if (null !== $previous && $crossings[$previous]['end'] >= $run['start']) {
                        $crossings[$previous]['end'] = $end;
                        $crossings[$previous]['vario'] = max($crossings[$previous]['vario'], round($run['vario'], 2));
                    } else {
                        $crossings[] = ['start' => $run['start'], 'end' => $end, 'vario' => round($run['vario'], 2), 'gain' => 0.0];
                    }
                }
                $run = null;
            }
        }

        return array_map(static fn (array $crossing): array => [...$crossing, 'gain' => round($altitude[$crossing['end']] - $altitude[$crossing['start']])], $crossings);
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
     * The phases on the task when it was finished (cut at the start and the finish), otherwise all
     * of them.
     *
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param list<Phase>                                                                                                                          $phases
     *
     * @return list<Phase>
     */
    private static function onTask(array $series, array $phases, ?TaskResult $task): array
    {
        if (null === $task || !$task->isCompleted()) {
            return $phases;
        }
        $from = (int) $task->start();
        $to = (int) $task->finish();
        $clipped = [];
        foreach ($phases as $phase) {
            $start = max($phase->start, $from);
            $end = min($phase->end, $to);
            if ($end <= $start) {
                continue;
            }
            if ($start === $phase->start && $end === $phase->end) {
                $clipped[] = $phase;
                continue;
            }
            $distance = 0.0;
            for ($i = $start + 1; $i <= $end; ++$i) {
                $distance += Geo::distance($series['latitude'][$i - 1], $series['longitude'][$i - 1], $series['latitude'][$i], $series['longitude'][$i]);
            }
            $gain = $series['altitude'][$end] - $series['altitude'][$start];
            $clipped[] = new Phase(
                $phase->type,
                $start,
                $end,
                $gain,
                $distance,
                Phase::GLIDE === $phase->type && -$gain >= self::MIN_GLIDE_LOSS ? $distance / -$gain : null,
                $phase->direction,
                Geo::distance($series['latitude'][$start], $series['longitude'][$start], $series['latitude'][$end], $series['longitude'][$end]),
                $phase->turns,
                $phase->radius,
                $phase->bank,
                $phase->windSpeed,
                $phase->windFrom,
            );
        }

        return $clipped;
    }

    /**
     * On the task when it was finished, otherwise on the whole flight: the spiral (average radius
     * and bank, and the radius of the best quarter of the thermals by climb, at least four of
     * them), the wind (drift of the circles, averaged as vectors) and the slope or wave lift.
     *
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param list<Phase>                                                                                                                          $phases
     *
     * @return array<string, int|float|null>
     */
    private static function circling(array $series, array $phases, ?TaskResult $task): array
    {
        $inside = self::onTask($series, $phases, $task);
        $from = null !== $task && $task->isCompleted() ? (int) $task->start() : 0;
        $to = null !== $task && $task->isCompleted() ? (int) $task->finish() : 0;
        $thermals = array_values(array_filter($inside, static fn (Phase $phase): bool => Phase::THERMAL === $phase->type && null !== $phase->radius));
        $lifts = array_values(array_filter($inside, static fn (Phase $phase): bool => Phase::LIFT === $phase->type));
        $time = array_sum(array_map(static fn (Phase $phase): int => $phase->duration(), $thermals));
        $weighted = static fn (callable $value): ?float => $time > 0 ? round(array_sum(array_map(static fn (Phase $phase): float => $value($phase) * $phase->duration(), $thermals)) / $time) : null;

        $target = null;
        if (\count($thermals) >= 4) {
            $best = $thermals;
            usort($best, static fn (Phase $a, Phase $b): int => $b->vario() <=> $a->vario());
            $best = \array_slice($best, 0, (int) ceil(\count($best) / 4));
            $target = round(array_sum(array_map(static fn (Phase $phase): float => (float) $phase->radius, $best)) / \count($best));
        }

        // Wind as the average of the drift vectors (towards which the circles move)
        $east = 0.0;
        $north = 0.0;
        $windTime = 0;
        foreach ($thermals as $phase) {
            if (null === $phase->windSpeed || null === $phase->windFrom) {
                continue;
            }
            $towards = deg2rad($phase->windFrom + 180.0);
            $east += sin($towards) * $phase->windSpeed * $phase->duration();
            $north += cos($towards) * $phase->windSpeed * $phase->duration();
            $windTime += $phase->duration();
        }
        $windSpeed = $windTime > 0 ? hypot($east, $north) / $windTime : null;

        // The same figures as the whole flight has, on this part
        $allThermals = array_values(array_filter($inside, static fn (Phase $phase): bool => Phase::THERMAL === $phase->type));
        $glides = array_values(array_filter($inside, static fn (Phase $phase): bool => Phase::GLIDE === $phase->type));
        $sum = static fn (array $list, callable $value): float => array_sum(array_map($value, $list));
        $circlingTime = $sum($allThermals, static fn (Phase $phase): int => $phase->duration());
        $span = null !== $task && $task->isCompleted() ? $to - $from : null;
        $glideTime = $sum($glides, static fn (Phase $phase): int => $phase->duration());
        $glideDistance = $sum($glides, static fn (Phase $phase): float => $phase->distance);
        $glideLoss = $sum($glides, static fn (Phase $phase): float => max(0.0, -$phase->gain));

        return [
            'phaseThermals' => \count($allThermals),
            'phaseCircling' => null !== $span && $span > 0 ? round($circlingTime / $span, 3) : null,
            'phaseClimb' => $circlingTime > 0 ? round($sum($allThermals, static fn (Phase $phase): float => $phase->gain) / $circlingTime, 2) : null,
            'phaseLeftShare' => $circlingTime > 0 ? round($sum(array_filter($allThermals, static fn (Phase $phase): bool => 'left' === $phase->direction), static fn (Phase $phase): int => $phase->duration()) / $circlingTime, 3) : null,
            'phaseGlideDistance' => round($glideDistance),
            'phaseGlideSpeed' => $glideTime > 0 ? round($glideDistance / $glideTime * 3.6, 1) : null,
            'phaseGlideRatio' => $glideLoss >= self::MIN_GLIDE_LOSS ? round($glideDistance / $glideLoss, 1) : null,
            'spiralRadius' => $weighted(static fn (Phase $phase): float => (float) $phase->radius),
            'spiralBank' => $weighted(static fn (Phase $phase): float => (float) $phase->bank),
            'spiralTarget' => $target,
            'windSpeed' => null !== $windSpeed ? round($windSpeed, 1) : null,
            'windFrom' => null !== $windSpeed && $windSpeed > 0.5 ? round(fmod(rad2deg(atan2($east, $north)) + 180.0 + 360.0, 360.0)) : null,
            'lifts' => \count($lifts),
            'liftGain' => round(array_sum(array_map(static fn (Phase $phase): float => $phase->gain, $lifts))),
        ];
    }

    /**
     * On the task when it was finished, otherwise on the whole flight: the band of the thermals
     * (average altitude at their entry and exit) and the detour of the glides (distance flown over
     * the straight lines between their ends, split at the turnpoints reached on the way).
     *
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param list<Phase>                                                                                                                          $phases
     * @param list<float>                                                                                                                          $altitude the real altitude, for the band ($series has the one without recoveries)
     *
     * @return array{bandBottom: ?float, bandTop: ?float, glideDetour: ?float}
     */
    private static function taskFlying(array $series, array $phases, ?TaskResult $task, array $altitude): array
    {
        $reached = null !== $task ? array_filter($task->reached, static fn (?int $index): bool => null !== $index) : [];
        $thermals = [];
        $flown = 0.0;
        $straight = 0.0;
        foreach (self::onTask($series, $phases, $task) as $phase) {
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
            ? round(array_sum(array_map(static fn (Phase $phase): float => $altitude['start' === $end ? $phase->start : $phase->end], $thermals)) / \count($thermals))
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
        // Zone of a point: a cylinder, or for 180° a half disc on one side of the line across the leg:
        // behind it for the start (left when crossing the line), beyond it for the finish (entered
        // when crossing the line). Measured against the IGC exports of Condor (2026-10-11): with the
        // finish half disc on the near side, the finish came 2 to 5 s before Condor's
        $inside = static function (int $i, Turnpoint $point, ?Turnpoint $from = null, ?Turnpoint $to = null, bool $beyond = false) use ($latitude, $longitude): bool {
            if (Geo::distance($latitude[$i], $longitude[$i], $point->latitude, $point->longitude) > $point->radius) {
                return false;
            }
            if (!$point->isLine() || null === $from || null === $to) {
                return true;
            }
            [$x, $y] = self::metres($point, $latitude[$i], $longitude[$i]);
            [$ux, $uy] = self::metres($from, $to->latitude, $to->longitude);

            $side = $x * $ux + $y * $uy;

            return $beyond ? $side >= 0 : $side <= 0;
        };

        $reached = array_fill(0, \count($turnpoints), null);
        $from = 0;
        for ($k = 2; $k <= $last; ++$k) {
            $point = $turnpoints[$k];
            for ($i = $from; $i < $count && !$inside($i, $point, $turnpoints[$k - 1], $point, $k === $last); ++$i) {
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
