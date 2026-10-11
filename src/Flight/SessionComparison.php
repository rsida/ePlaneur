<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;

/**
 * Every flight of a session compared along the task at once (FlightComparison does two): at each
 * point of the course, each pilot's time since their start against the fastest time any pilot
 * took to get there, the "virtual leader". The gap of a pilot only grows where they lose time on
 * the best of the session; the stretches where it grows most are their key moments.
 *
 * @phpstan-import-type Stretch from FlightComparison
 */
final readonly class SessionComparison
{
    /** Length of the stretches of the key moments, metres. */
    private const int STRETCH = 10000;

    /** Shortest loss worth a key moment, seconds. */
    private const int MIN_MOMENT = 30;

    /** Shortest lead worth a chapter of the race, metres: shorter changes of leader are noise. */
    private const int LEAD_RUN = 5000;

    /**
     * @param list<Flight>    $flights ranked as the session (FlightSession), those that started
     * @param list<list<int>> $times   index in the series where each flight reaches each STEP
     * @param list<int>       $starts  index of the start of each flight
     */
    private function __construct(
        public array $flights,
        public array $times,
        public array $starts,
        public float $distance,
    ) {
    }

    /**
     * @param list<Flight> $flights ranked (FlightSession::of()->flights)
     */
    public static function of(array $flights): ?self
    {
        $kept = [];
        $times = [];
        $starts = [];
        foreach ($flights as $flight) {
            $analysis = $flight->getAnalysis();
            $start = $analysis['task']['turnpoints'][1]['reached'] ?? null;
            if (null === $start) {
                continue;
            }
            $kept[] = $flight;
            $times[] = FlightComparison::progressTimes($analysis);
            $starts[] = $start;
        }
        if (\count($kept) < 2) {
            return null;
        }

        return new self($kept, $times, $starts, (float) ($kept[0]->getAnalysis()['task']['distance'] ?? 0));
    }

    /**
     * Fastest time from the start to each STEP of the task, among the flights that reached it.
     *
     * @return list<int>
     */
    public function best(): array
    {
        $best = [];
        foreach ($this->times as $f => $times) {
            foreach ($times as $k => $index) {
                $elapsed = $index - $this->starts[$f];
                $best[$k] = isset($best[$k]) ? min($best[$k], $elapsed) : $elapsed;
            }
        }
        ksort($best);

        return array_values($best);
    }

    /**
     * Gap of each flight to the virtual leader at each STEP it reached, seconds (0 or more).
     *
     * @return list<list<int>>
     */
    public function gaps(): array
    {
        $best = $this->best();

        return array_map(fn (array $times, int $f): array => array_map(fn (int $index, int $k): int => $index - $this->starts[$f] - $best[$k], $times, array_keys($times)), $this->times, array_keys($this->times));
    }

    /**
     * Legs of the task with each pilot's time on it and the best time.
     *
     * @return list<array{from: string, to: string, distance: float, times: list<?int>, best: ?int}>
     */
    public function legs(): array
    {
        $legs = [];
        foreach ($this->flights[0]->getAnalysis()['task']['legs'] ?? [] as $l => $leg) {
            $times = array_map(static fn (Flight $flight): ?int => $flight->getAnalysis()['task']['legs'][$l]['duration'] ?? null, $this->flights);
            $known = array_filter($times, static fn (?int $time): bool => null !== $time);
            $legs[] = ['from' => $leg['from'], 'to' => $leg['to'], 'distance' => (float) $leg['distance'], 'times' => $times, 'best' => [] !== $known ? min($known) : null];
        }

        return $legs;
    }

    /**
     * For each flight, the stretch of the task where it lost the most on the virtual leader, with
     * what the pilot did there and what the fastest pilot on that stretch did; null when no stretch
     * cost MIN_MOMENT seconds.
     *
     * @return list<array{from: int, to: int, loss: int, pilot: Stretch, fastest: Flight, best: Stretch}|null>
     */
    public function moments(): array
    {
        $gaps = $this->gaps();
        $width = max(4, intdiv((int) min(self::STRETCH, $this->distance / 4), FlightComparison::STEP));
        $moments = [];
        foreach ($gaps as $f => $gap) {
            $worst = null;
            for ($k = 0; $k + $width < \count($gap); ++$k) {
                $loss = $gap[$k + $width] - $gap[$k];
                if ($loss >= self::MIN_MOMENT && (null === $worst || $loss > $worst[1])) {
                    $worst = [$k, $loss];
                }
            }
            if (null === $worst) {
                $moments[] = null;
                continue;
            }
            [$k, $loss] = $worst;
            // The pilot who flew this stretch the fastest
            $fastest = null;
            foreach ($this->times as $g => $times) {
                if (!isset($times[$k + $width])) {
                    continue;
                }
                $duration = $times[$k + $width] - $times[$k];
                if (null === $fastest || $duration < $fastest[1]) {
                    $fastest = [$g, $duration];
                }
            }
            $g = $fastest[0] ?? $f;
            $moments[] = [
                'from' => $k * FlightComparison::STEP,
                'to' => ($k + $width) * FlightComparison::STEP,
                'loss' => $loss,
                'pilot' => FlightComparison::describe($this->flights[$f]->getAnalysis(), $this->times[$f][$k], $this->times[$f][$k + $width]),
                'fastest' => $this->flights[$g],
                'best' => FlightComparison::describe($this->flights[$g]->getAnalysis(), $this->times[$g][$k], $this->times[$g][$k + $width]),
            ];
        }

        return $moments;
    }

    /**
     * Who led the race along the task, as with all pilots starting together: at each STEP, the
     * pilot who got there in the shortest time since their start. Leads held for less than
     * LEAD_RUN metres join the run before them (the first one the run after it). Each run gives
     * where it starts and ends along the task (metres), its leader, the leader's time since the
     * start where the run begins, and their lead on the second at its end (seconds; null alone).
     *
     * @return list<array{from: int, to: int, leader: Flight, flight: int, elapsed: int, lead: ?int}>
     */
    public function story(): array
    {
        $steps = \count($this->best());
        $leaders = [];
        // From the first STEP: at the start line every pilot is at zero
        for ($k = 1; $k < $steps; ++$k) {
            $elapsed = [];
            foreach ($this->times as $f => $times) {
                if (isset($times[$k])) {
                    $elapsed[$f] = $times[$k] - $this->starts[$f];
                }
            }
            asort($elapsed);
            $leaders[$k] = array_key_first($elapsed);
        }
        if ([] === $leaders) {
            return [];
        }

        // Runs of the same leader, the short ones merged into their neighbour
        $runs = [];
        foreach ($leaders as $k => $f) {
            if ([] !== $runs && $runs[array_key_last($runs)][2] === $f) {
                $runs[array_key_last($runs)][1] = $k;
            } else {
                $runs[] = [$k, $k, $f];
            }
        }
        $min = intdiv(self::LEAD_RUN, FlightComparison::STEP);
        do {
            $merged = false;
            foreach ($runs as $r => [$from, $to]) {
                if (\count($runs) > 1 && $to - $from + 1 < $min) {
                    $into = $r > 0 ? $r - 1 : $r + 1;
                    $runs[$into] = [min($runs[$into][0], $from), max($runs[$into][1], $to), $runs[$into][2]];
                    unset($runs[$r]);
                    $runs = array_values($runs);
                    $merged = true;
                    break;
                }
            }
            for ($r = \count($runs) - 1; $r > 0; --$r) {
                if ($runs[$r][2] === $runs[$r - 1][2]) {
                    $runs[$r - 1][1] = $runs[$r][1];
                    array_splice($runs, $r, 1);
                }
            }
        } while ($merged);

        return array_map(function (array $run): array {
            [$from, $to, $f] = $run;
            $elapsed = [];
            foreach ($this->times as $g => $times) {
                if (isset($times[$to])) {
                    $elapsed[$g] = $times[$to] - $this->starts[$g];
                }
            }
            sort($elapsed);

            return [
                'from' => (1 === $from ? 0 : $from) * FlightComparison::STEP,
                'to' => $to * FlightComparison::STEP,
                'leader' => $this->flights[$f],
                'flight' => $f,
                'elapsed' => $this->times[$f][$from] - $this->starts[$f],
                'lead' => isset($elapsed[1]) ? $elapsed[1] - $elapsed[0] : null,
            ];
        }, $runs);
    }

    /**
     * What the charts draw: the task distance of each STEP (km), and for each flight its gap to the
     * virtual leader and its altitude at each STEP it reached.
     *
     * @return array{km: list<float>, flights: list<array{id: ?int, pilot: string, competitionId: ?string, gap: list<int>, altitude: list<float>}>}
     */
    public function chartData(): array
    {
        $gaps = $this->gaps();
        $steps = \count($this->best());

        return [
            'km' => array_map(static fn (int $k): float => $k * FlightComparison::STEP / 1000, range(0, max(0, $steps - 1))),
            'flights' => array_map(fn (Flight $flight, int $f): array => [
                'id' => $flight->getId(),
                'pilot' => $flight->getPilot(),
                'competitionId' => $flight->getCompetitionId(),
                'gap' => $gaps[$f],
                'altitude' => array_map(static fn (int $index): float => round($flight->getAnalysis()['series']['altitude'][$index]), $this->times[$f]),
            ], $this->flights, array_keys($this->flights)),
        ];
    }
}
