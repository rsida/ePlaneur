<?php

declare(strict_types=1);

namespace App\Flight\Analysis;

use App\Flight\Geo\Geo;
use App\Flight\Track;

/**
 * Result of FlightAnalyzer: the flight resampled to one point per second, its phases, statistics
 * and task. toArray() is what the flight viewer reads.
 */
final readonly class FlightAnalysis
{
    /**
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param list<Phase>                                                                                                                          $phases
     * @param array<string, int|float|null>                                                                                                        $statistics
     */
    public function __construct(
        public Track $track,
        public array $series,
        public array $phases,
        public array $statistics,
        public ?TaskResult $task,
        public ?Launch $launch = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $round = static fn (array $values, int $precision): array => array_map(static fn (float $value): float => round($value, $precision), $values);

        return [
            'exactTimes' => $this->track->exactTimes,
            'launch' => $this->launch?->value,
            'water' => $this->track->water,
            'statistics' => $this->statistics,
            'series' => [
                'time' => $this->series['time'],
                'latitude' => $round($this->series['latitude'], 6),
                'longitude' => $round($this->series['longitude'], 6),
                'altitude' => $round($this->series['altitude'], 1),
                'speed' => $round($this->series['speed'], 1),
                'vario' => $round($this->series['vario'], 2),
            ],
            'phases' => array_map(static fn (Phase $phase): array => [
                'type' => $phase->type,
                'start' => $phase->start,
                'end' => $phase->end,
                'duration' => $phase->duration(),
                'gain' => round($phase->gain),
                'vario' => round($phase->vario(), 2),
                'distance' => round($phase->distance),
                'speed' => round($phase->speed(), 1),
                'glideRatio' => null !== $phase->glideRatio ? round($phase->glideRatio, 1) : null,
                'direction' => $phase->direction,
                'straight' => round($phase->straight),
            ], $this->phases),
            'task' => null === $this->task ? null : $this->taskArray($this->task),
        ];
    }

    /**
     * The last glide of a finished task, from the end of the last thermal to the finish: how high
     * the glider arrived, and what that height cost at the climb rate of that last thermal (height
     * above the finish point: the minimum height of the task is not read from the file yet).
     *
     * @return array<string, mixed>|null
     */
    private function finalGlide(TaskResult $task): ?array
    {
        $finish = $task->finish();
        if (!$task->isCompleted() || null === $finish) {
            return null;
        }
        $thermals = array_filter($this->phases, static fn (Phase $phase): bool => Phase::THERMAL === $phase->type && $phase->end <= $finish && $phase->end > (int) $task->start());
        $last = [] !== $thermals ? end($thermals) : null;
        $from = null !== $last ? $last->end : (int) $task->start();
        $series = $this->series;
        $distance = 0.0;
        for ($i = $from + 1; $i <= $finish; ++$i) {
            $distance += Geo::distance($series['latitude'][$i - 1], $series['longitude'][$i - 1], $series['latitude'][$i], $series['longitude'][$i]);
        }
        $duration = $finish - $from;
        $height = $series['altitude'][$finish] - $task->turnpoints[array_key_last($task->turnpoints)]->altitude;
        $climb = null !== $last && $last->vario() > 0 ? $last->vario() : null;

        return [
            'from' => $from,
            'climb' => null !== $climb ? round($climb, 2) : null,
            'distance' => round($distance),
            'duration' => $duration,
            'speed' => $duration > 0 ? round($distance / $duration * 3.6, 1) : null,
            'startAltitude' => round($series['altitude'][$from]),
            'arrivalAltitude' => round($series['altitude'][$finish]),
            'height' => round($height),
            'heightTime' => null !== $climb && $height > 0 ? (int) round($height / $climb) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskArray(TaskResult $task): array
    {
        $legs = [];
        $points = $task->turnpoints;
        for ($i = 1; $i < \count($points) - 1; ++$i) {
            $from = $task->reached[$i];
            $to = $task->reached[$i + 1] ?? null;
            $distance = Geo::distance($points[$i]->latitude, $points[$i]->longitude, $points[$i + 1]->latitude, $points[$i + 1]->longitude);
            $duration = null !== $from && null !== $to ? $to - $from : null;
            $legs[] = [
                'from' => $points[$i]->name,
                'to' => $points[$i + 1]->name,
                'distance' => round($distance),
                'duration' => $duration,
                'speed' => null !== $duration && $duration > 0 ? round($distance / $duration * 3.6, 1) : null,
            ];
        }
        $duration = $task->isCompleted() ? $task->finish() - $task->start() : null;
        $series = $this->series;

        return [
            'turnpoints' => array_map(static fn ($point, $reached): array => [
                'name' => $point->name,
                'latitude' => round($point->latitude, 6),
                'longitude' => round($point->longitude, 6),
                'altitude' => round($point->altitude),
                'radius' => $point->radius,
                'reached' => $reached,
                // Altitude and ground speed when the point was reached
                'reachedAltitude' => null !== $reached ? round($series['altitude'][$reached]) : null,
                'reachedSpeed' => null !== $reached ? round($series['speed'][$reached], 1) : null,
            ], $points, $task->reached),
            'finalGlide' => $this->finalGlide($task),
            'legs' => $legs,
            'distance' => round($task->distance),
            'completed' => $task->isCompleted(),
            'duration' => $duration,
            'speed' => null !== $duration && $duration > 0 ? round($task->distance / $duration * 3.6, 1) : null,
        ];
    }
}
