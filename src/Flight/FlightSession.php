<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;

/**
 * The flights of one network session: every flight on the same flight plan (Condor task file), the
 * way the club names its sessions ("C3-c060_Laragne-Ventoux-Fayence..."). Ranked like a race: the
 * pilots who finished by task speed, then the others by distance flown.
 */
final readonly class FlightSession
{
    /**
     * @param list<Flight> $flights ranked
     */
    private function __construct(
        public ?string $flightPlan,
        public array $flights,
    ) {
    }

    /**
     * @param list<Flight> $flights
     */
    public static function of(array $flights): self
    {
        usort($flights, static fn (Flight $a, Flight $b): int => [null === $a->getTaskSpeed(), -($a->getTaskSpeed() ?? 0), -$a->getDistance(), $a->getId()]
            <=> [null === $b->getTaskSpeed(), -($b->getTaskSpeed() ?? 0), -$b->getDistance(), $b->getId()]);

        return new self($flights[0]->getFlightPlan(), $flights);
    }

    /**
     * The best pilot of the session on what the training path works on, not only speed. A title is
     * left out when no flight has its value.
     *
     * @return list<array{title: string, flight: Flight, value: float, unit: string, decimals: int}>
     */
    public function podium(): array
    {
        $titles = [
            ['Le plus rapide', static fn (Flight $flight): ?float => $flight->getTaskSpeed(), 'km/h sur le circuit', 1, true],
            ['Meilleur grimpeur', static fn (Flight $flight): ?float => self::statistic($flight, 'climbAverage'), 'm/s en thermique', 1, true],
            ['Meilleure finesse', static fn (Flight $flight): ?float => self::statistic($flight, 'glideRatio'), 'en transition', 0, true],
            ['Le moins en spirale', static fn (Flight $flight): ?float => null !== ($share = self::statistic($flight, 'circlingShare')) ? $share * 100 : null, '% du vol en spirale', 0, false],
        ];
        $podium = [];
        foreach ($titles as [$title, $value, $unit, $decimals, $highest]) {
            $best = null;
            foreach ($this->flights as $flight) {
                $candidate = $value($flight);
                if (null !== $candidate && (null === $best || ($highest ? $candidate > $best[1] : $candidate < $best[1]))) {
                    $best = [$flight, $candidate];
                }
            }
            if (null !== $best) {
                $podium[] = ['title' => $title, 'flight' => $best[0], 'value' => $best[1], 'unit' => $unit, 'decimals' => $decimals];
            }
        }

        return $podium;
    }

    /**
     * What the session replay draws: the task and, for each flight, its track (one point per second
     * from `start`, seconds since the beginning of the recording) and the time it started the task.
     *
     * @return array{task: mixed, flights: list<array<string, mixed>>}
     */
    public function replayData(): array
    {
        $task = null;
        $tracks = [];
        foreach ($this->flights as $member) {
            $analysis = $member->getAnalysis();
            $task ??= $analysis['task'] ?? null;
            $time = $analysis['series']['time'];
            $started = $analysis['task']['turnpoints'][1]['reached'] ?? null;
            $tracks[] = [
                'id' => $member->getId(),
                'pilot' => $member->getPilot(),
                'competitionId' => $member->getCompetitionId(),
                'start' => $time[0] ?? 0,
                'taskStart' => null !== $started ? $time[$started] : null,
                'latitude' => $analysis['series']['latitude'],
                'longitude' => $analysis['series']['longitude'],
                'altitude' => $analysis['series']['altitude'],
                // Ground speed, for the values of each pilot and the bank of their glider
                'speed' => $analysis['series']['speed'],
            ];
        }

        return ['task' => $task, 'flights' => $tracks];
    }

    /**
     * Every thermal of the flights, for the thermal map of the race: its centre (the average
     * position while circling), climb, altitudes and when it was entered and left, in seconds since
     * the pilot crossed the start line (pilots join the server at different times, so their
     * recordings do not share a clock; thermals before the start are left out).
     *
     * @return array{task: mixed, thermals: list<array<string, mixed>>}
     */
    public function thermalMap(): array
    {
        $task = null;
        $thermals = [];
        foreach ($this->flights as $flight) {
            $analysis = $flight->getAnalysis();
            $task ??= $analysis['task'] ?? null;
            $series = $analysis['series'];
            $start = $analysis['task']['turnpoints'][1]['reached'] ?? null;
            if (null === $start) {
                continue;
            }
            foreach ($analysis['phases'] as $phase) {
                if ('thermal' !== $phase['type'] || $phase['end'] <= $phase['start'] || $phase['end'] < $start) {
                    continue;
                }
                $length = $phase['end'] - $phase['start'] + 1;
                $thermals[] = [
                    'flight' => $flight->getId(),
                    'pilot' => $flight->getCompetitionId() ?? $flight->getPilot(),
                    'latitude' => round(array_sum(\array_slice($series['latitude'], $phase['start'], $length)) / $length, 5),
                    'longitude' => round(array_sum(\array_slice($series['longitude'], $phase['start'], $length)) / $length, 5),
                    'climb' => $phase['vario'],
                    'bottom' => round($series['altitude'][$phase['start']]),
                    'top' => round($series['altitude'][$phase['end']]),
                    'from' => max(0, $series['time'][$phase['start']] - $series['time'][$start]),
                    'to' => $series['time'][$phase['end']] - $series['time'][$start],
                ];
            }
        }

        return ['task' => $task, 'thermals' => $thermals];
    }

    /**
     * Where a flight stands among the flights of the session on the task, as Condor Club says it:
     * its rank for the climb in the thermals and for the speed of the transitions (the best first),
     * among the flights that have the value.
     *
     * @return array{count: int, climb: ?int, glide: ?int}
     */
    public function ranks(Flight $flight): array
    {
        $rank = function (string $name) use ($flight): ?int {
            $own = self::statistic($flight, $name);
            if (null === $own) {
                return null;
            }
            $better = array_filter($this->flights, static fn (Flight $other): bool => $other !== $flight && (self::statistic($other, $name) ?? -\INF) > $own);

            return \count($better) + 1;
        };

        return ['count' => \count($this->flights), 'climb' => $rank('phaseClimb'), 'glide' => $rank('phaseGlideSpeed')];
    }

    public static function statistic(Flight $flight, string $name): ?float
    {
        $value = $flight->getAnalysis()['statistics'][$name] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
