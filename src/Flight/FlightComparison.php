<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;
use App\Flight\Geo\Geo;

/**
 * Two flights of the same task compared along the task: where along the course one pilot gains or
 * loses time on the other, leg by leg and kilometre by kilometre, and how (time circling, climb
 * rate, glide speed).
 *
 * Each point of a flight between its start and its finish has a progress: the distance of the task
 * done, i.e. the legs completed plus the part of the current leg flown towards its next point. The
 * time of a flight at a distance d is when its progress first reaches d, counted from its own start:
 * the gap at d is the difference of these times (positive: the first flight is behind).
 *
 * @phpstan-type Stretch array{duration: int, circling: int, thermals: int, climb: ?float, glideSpeed: ?float, from: float, to: float}
 */
final readonly class FlightComparison
{
    /** Metres between two points of the gap series. */
    public const int STEP = 250;

    /** Length of the stretches where the most time is gained or lost, metres (a quarter of a short task). */
    private const int STRETCH = 10000;

    /** Shortest gain or loss worth a key moment, seconds. */
    private const int MIN_MOMENT = 30;

    /**
     * @param list<list<int>> $times  index in the series where the progress reaches each STEP, per
     *                                flight (up to the last step it reached)
     * @param list<int>       $starts index of the start of each flight
     */
    private function __construct(
        public Flight $flight,
        public Flight $reference,
        public array $times,
        public array $starts,
        public float $distance,
    ) {
    }

    /**
     * Null when the flights cannot be compared along a task: different flight plans, no task, or a
     * flight that never started it.
     */
    public static function of(Flight $flight, Flight $reference): ?self
    {
        if (null === $flight->getFlightPlan() || $flight->getFlightPlan() !== $reference->getFlightPlan()) {
            return null;
        }
        $times = [];
        $starts = [];
        foreach ([$flight, $reference] as $member) {
            $task = $member->getAnalysis()['task'] ?? null;
            $start = $task['turnpoints'][1]['reached'] ?? null;
            if (null === $start) {
                return null;
            }
            $starts[] = $start;
            $times[] = self::progressTimes($member->getAnalysis());
        }

        return new self($flight, $reference, $times, $starts, (float) ($flight->getAnalysis()['task']['distance'] ?? 0));
    }

    /**
     * Gap at each STEP of the task reached by both flights, seconds (positive: the flight is behind
     * the reference).
     *
     * @return list<int>
     */
    public function gaps(): array
    {
        $gaps = [];
        foreach ($this->times[0] as $k => $time) {
            $other = $this->times[1][$k] ?? null;
            if (null === $other) {
                break;
            }
            $gaps[] = ($time - $this->starts[0]) - ($other - $this->starts[1]);
        }

        return $gaps;
    }

    /**
     * Legs of the task with the time of each flight and the gap made on the leg.
     *
     * @return list<array{from: string, to: string, distance: float, flight: ?int, reference: ?int, gap: ?int}>
     */
    public function legs(): array
    {
        $legs = [];
        $references = $this->reference->getAnalysis()['task']['legs'] ?? [];
        foreach ($this->flight->getAnalysis()['task']['legs'] ?? [] as $k => $leg) {
            $other = $references[$k]['duration'] ?? null;
            $legs[] = [
                'from' => $leg['from'],
                'to' => $leg['to'],
                'distance' => (float) $leg['distance'],
                'flight' => $leg['duration'],
                'reference' => $other,
                'gap' => null !== $leg['duration'] && null !== $other ? $leg['duration'] - $other : null,
            ];
        }

        return $legs;
    }

    /**
     * Where the time was lost or gained, leg by leg: the gap made on each leg completed by both
     * flights, split into the time spent circling (more or weaker thermals) and the time flying
     * straight (slower glides, detours), with the gap before and after the leg (a waterfall; positive:
     * the flight is behind), where the leg lies along the task (metres) and what each pilot did on
     * it.
     *
     * @return list<array{from: string, to: string, start: float, end: float, circling: int, straight: int, gap: int, before: int, after: int, flight: Stretch, reference: Stretch}>
     */
    public function waterfall(): array
    {
        $rows = [];
        $total = 0;
        $points = $this->flight->getAnalysis()['task']['turnpoints'];
        $others = $this->reference->getAnalysis()['task']['turnpoints'];
        $legs = $this->flight->getAnalysis()['task']['legs'] ?? [];
        $along = 0.0;
        for ($k = 1; $k < \count($points) - 1; ++$k) {
            $mine = [$points[$k]['reached'] ?? null, $points[$k + 1]['reached'] ?? null];
            $theirs = [$others[$k]['reached'] ?? null, $others[$k + 1]['reached'] ?? null];
            if (\in_array(null, [...$mine, ...$theirs], true)) {
                break;
            }
            $flight = self::describe($this->flight->getAnalysis(), $mine[0], $mine[1]);
            $reference = self::describe($this->reference->getAnalysis(), $theirs[0], $theirs[1]);
            $circling = $flight['circling'] - $reference['circling'];
            $straight = ($flight['duration'] - $flight['circling']) - ($reference['duration'] - $reference['circling']);
            $start = $along;
            $along += (float) ($legs[$k - 1]['distance'] ?? 0);
            $rows[] = [
                'from' => $points[$k]['name'],
                'to' => $points[$k + 1]['name'],
                'start' => $start,
                'end' => $along,
                'circling' => $circling,
                'straight' => $straight,
                'gap' => $circling + $straight,
                'before' => $total,
                'after' => $total += $circling + $straight,
                'flight' => $flight,
                'reference' => $reference,
            ];
        }

        return $rows;
    }

    /**
     * Stretches of the task where the flight lost (or gained) the most time on the reference, the
     * biggest first, with what each pilot did there. Stretches do not overlap.
     *
     * @return list<array{from: int, to: int, gap: int, flight: Stretch, reference: Stretch}>
     */
    public function moments(bool $losses): array
    {
        $gaps = $this->gaps();
        $width = max(4, intdiv((int) min(self::STRETCH, $this->distance / 4), self::STEP));
        $candidates = [];
        for ($k = 0; $k + $width < \count($gaps); ++$k) {
            $change = $gaps[$k + $width] - $gaps[$k];
            if ($losses ? $change >= self::MIN_MOMENT : $change <= -self::MIN_MOMENT) {
                $candidates[] = [$k, $change];
            }
        }
        usort($candidates, static fn (array $a, array $b): int => $losses ? $b[1] <=> $a[1] : $a[1] <=> $b[1]);

        $moments = [];
        $taken = [];
        foreach ($candidates as [$k, $change]) {
            foreach ($taken as $other) {
                if (abs($other - $k) < $width) {
                    continue 2;
                }
            }
            $taken[] = $k;
            $moments[] = [
                'from' => $k * self::STEP,
                'to' => ($k + $width) * self::STEP,
                'gap' => $change,
                'flight' => self::describe($this->flight->getAnalysis(), $this->times[0][$k], $this->times[0][$k + $width]),
                'reference' => self::describe($this->reference->getAnalysis(), $this->times[1][$k], $this->times[1][$k + $width]),
            ];
            if (3 === \count($moments)) {
                break;
            }
        }

        return $moments;
    }

    /**
     * Thermals of a flight between its start and its finish, with where they are along the task.
     *
     * @return list<array{at: int, start: int, duration: int, gain: float, vario: float}>
     */
    public function thermals(int $which): array
    {
        $analysis = (0 === $which ? $this->flight : $this->reference)->getAnalysis();
        $times = $this->times[$which];
        $points = $analysis['task']['turnpoints'];
        $finish = $points[\count($points) - 1]['reached'] ?? \PHP_INT_MAX;
        $thermals = [];
        foreach ($analysis['phases'] as $phase) {
            if ('thermal' !== $phase['type'] || $phase['start'] < $this->starts[$which] || $phase['start'] > $finish) {
                continue;
            }
            // Last step of the task reached when the thermal begins
            $k = 0;
            while (isset($times[$k + 1]) && $times[$k + 1] <= $phase['start']) {
                ++$k;
            }
            $thermals[] = ['at' => $k * self::STEP, 'start' => (int) $phase['start'], 'duration' => (int) $phase['duration'], 'gain' => (float) $phase['gain'], 'vario' => (float) $phase['vario']];
        }

        return $thermals;
    }

    /**
     * Index of each STEP of the task in the series of a flight: the first point where its progress
     * reaches it, from the start to the finish (or to the last point when it did not finish).
     *
     * @param array<string, mixed> $analysis FlightAnalysis::toArray()
     *
     * @return list<int>
     */
    public static function progressTimes(array $analysis): array
    {
        $series = $analysis['series'];
        $points = $analysis['task']['turnpoints'];
        $count = \count($series['time']);
        $times = [];
        $done = 0.0; // distance of the legs completed
        $next = 2; // index of the point the pilot flies to
        $end = $points[\count($points) - 1]['reached'] ?? $count - 1;
        for ($i = $points[1]['reached']; $i <= $end; ++$i) {
            while ($next < \count($points) && null !== $points[$next]['reached'] && $points[$next]['reached'] <= $i) {
                $done += self::leg($points, $next);
                ++$next;
            }
            $progress = $next < \count($points)
                ? $done + max(0.0, self::leg($points, $next) - max(0.0, Geo::distance($series['latitude'][$i], $series['longitude'][$i], $points[$next]['latitude'], $points[$next]['longitude']) - self::cut($points, $next)))
                : $done;
            while (\count($times) * self::STEP <= $progress) {
                $times[] = $i;
            }
        }

        return $times;
    }

    /**
     * @param list<array{latitude: float, longitude: float, radius?: float, angle?: float}> $points
     */
    private static function leg(array $points, int $to): float
    {
        return max(0.0, Geo::distance($points[$to - 1]['latitude'], $points[$to - 1]['longitude'], $points[$to]['latitude'], $points[$to]['longitude']) - self::cut($points, $to));
    }

    /**
     * Metres taken off the leg to the point $to: the radius of a cylinder finish (TaskResult::leg()).
     *
     * @param list<array{latitude: float, longitude: float, radius?: float, angle?: float}> $points
     */
    private static function cut(array $points, int $to): float
    {
        $point = $points[$to];

        return $to === \count($points) - 1 && abs((float) ($point['angle'] ?? 360) - 180.0) >= 1.0 ? (float) ($point['radius'] ?? 0) : 0.0;
    }

    /**
     * What a pilot did between two points: time, time circling and climb rate in thermals, speed
     * and height lost in glides, altitude at both ends.
     *
     * @param array<string, mixed> $analysis
     *
     * @return Stretch
     */
    public static function describe(array $analysis, int $from, int $to): array
    {
        $series = $analysis['series'];
        $circling = 0;
        $gain = 0.0;
        $thermals = 0;
        $inThermal = [];
        foreach ($analysis['phases'] as $phase) {
            if ('thermal' !== $phase['type']) {
                continue;
            }
            $a = max($from, $phase['start']);
            $b = min($to, $phase['end']);
            if ($b - $a > 0) {
                $circling += $b - $a;
                $gain += $series['altitude'][$b] - $series['altitude'][$a];
                ++$thermals;
                for ($i = $a + 1; $i <= $b; ++$i) {
                    $inThermal[$i] = true;
                }
            }
        }
        // Distance of the glides only: circling covers distance without going anywhere
        $distance = 0.0;
        for ($i = $from + 1; $i <= $to; ++$i) {
            if (!isset($inThermal[$i])) {
                $distance += Geo::distance($series['latitude'][$i - 1], $series['longitude'][$i - 1], $series['latitude'][$i], $series['longitude'][$i]);
            }
        }
        $straight = $to - $from - $circling;

        return [
            'duration' => $to - $from,
            'circling' => $circling,
            'thermals' => $thermals,
            'climb' => $circling > 0 ? round($gain / $circling, 1) : null,
            'glideSpeed' => $straight > 0 ? round($distance / $straight * 3.6) : null,
            'from' => round($series['altitude'][$from]),
            'to' => round($series['altitude'][$to]),
        ];
    }
}
