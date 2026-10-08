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
            ], $this->phases),
            'task' => null === $this->task ? null : $this->taskArray($this->task),
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

        return [
            'turnpoints' => array_map(static fn ($point, $reached): array => [
                'name' => $point->name,
                'latitude' => round($point->latitude, 6),
                'longitude' => round($point->longitude, 6),
                'altitude' => round($point->altitude),
                'radius' => $point->radius,
                'reached' => $reached,
            ], $points, $task->reached),
            'legs' => $legs,
            'distance' => round($task->distance),
            'completed' => $task->isCompleted(),
            'duration' => $duration,
            'speed' => null !== $duration && $duration > 0 ? round($task->distance / $duration * 3.6, 1) : null,
        ];
    }
}
