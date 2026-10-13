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
    /** Straight lift far above the ground and away from the slopes is wave (isWave()). */
    private const float WAVE_HEIGHT = 600.0;
    private const float SLOPE_REACH = 500.0;

    /** Height of the bands of the climb by altitude, metres, and the seconds a band needs. */
    private const int CLIMB_BAND = 200;
    private const int CLIMB_BAND_MIN_TIME = 20;

    /**
     * @param array{time: list<int>, latitude: list<float>, longitude: list<float>, altitude: list<float>, speed: list<float>, vario: list<float>} $series
     * @param list<Phase>                                                                                                                          $phases
     * @param array<string, int|float|null>                                                                                                        $statistics
     * @param list<?float>|null                                                                                                                    $ground     height of the ground under each second (Terrain), null when not known
     * @param array{cell?: int, cells?: list<array{float, float, float}>, wind?: array{from: float, speed: float}}                                 $slopes     slopes facing the wind of the flight, with the lift expected (FlightTerrain)
     * @param array{bank: list<float>, pitch: list<float>}|null                                                                                    $attitude   bank and pitch of each second, degrees (Condor 3 .ftr)
     * @param list<array{start: int, end: int, vario: float, gain: float}>                                                                         $crossings  lift crossed in straight flight during the glides
     * @param list<float>|null                                                                                                                     $climb      altitude of each second without the height recoveries (null: none)
     */
    public function __construct(
        public Track $track,
        public array $series,
        public array $phases,
        public array $statistics,
        public ?TaskResult $task,
        public ?Launch $launch = null,
        public array $crossings = [],
        public ?array $ground = null,
        public array $slopes = [],
        public ?array $attitude = null,
        public ?array $climb = null,
    ) {
    }

    /**
     * The same analysis with the height of the ground under the track and the slopes expected to
     * work in the wind of the flight.
     *
     * @param list<?float>                                                                                         $ground
     * @param array{cell?: int, cells?: list<array{float, float, float}>, wind?: array{from: float, speed: float}} $slopes
     */
    public function withTerrain(array $ground, array $slopes): self
    {
        return new self($this->track, $this->series, $this->phases, $this->statistics, $this->task, $this->launch, $this->crossings, $ground, $slopes, $this->attitude, $this->climb);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $round = static fn (array $values, int $precision): array => array_map(static fn (float $value): float => round($value, $precision), $values);

        return [
            'exactTimes' => $this->track->exactTimes,
            // Clock time of the task in Condor at the first second of the series, seconds of the day
            'clock' => null !== $this->track->clock ? (int) round(fmod($this->track->clock + $this->track->fixes[0]->time, 86400.0)) : null,
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
                'bank' => null !== $this->attitude ? $round($this->attitude['bank'], 1) : null,
                'pitch' => null !== $this->attitude ? $round($this->attitude['pitch'], 1) : null,
                'ground' => null !== $this->ground ? array_map(static fn (?float $value): ?float => null !== $value ? round($value) : null, $this->ground) : null,
            ],
            'phases' => array_map(fn (Phase $phase): array => [
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
                'turns' => $phase->turns,
                'radius' => $phase->radius,
                'bank' => $phase->bank,
                'windSpeed' => $phase->windSpeed,
                'windFrom' => $phase->windFrom,
                'centring' => $phase->centring,
                'entry' => $phase->entry,
                'exit' => $phase->exit,
                // Straight lift: wave when high above the ground, slope lift otherwise (null: ground not known)
                'wave' => Phase::LIFT === $phase->type ? $this->isWave($phase) : null,
            ], $this->phases),
            'slopes' => $this->slopes,
            'crossings' => $this->crossings,
            'climbByAltitude' => $this->climbByAltitude(),
            'task' => null === $this->task ? null : $this->taskArray($this->task),
            // Height recoveries of Condor: second of the series and metres
            'recoveries' => array_map(fn (array $recovery): array => ['at' => (int) ceil($recovery['time'] - $this->track->fixes[0]->time), 'height' => round($recovery['height'])], $this->track->recoveries),
            // What Condor computed itself (IGC exported by Condor): the official figures
            'condor' => $this->track->report?->toArray(),
        ];
    }

    /**
     * Straight lift is wave when it was on average WAVE_HEIGHT metres or more above the ground and
     * passed no slope expected to work (within SLOPE_REACH metres), slope lift otherwise.
     */
    private function isWave(Phase $phase): ?bool
    {
        $heights = [];
        for ($i = $phase->start; $i <= $phase->end; ++$i) {
            if (null !== ($this->ground[$i] ?? null)) {
                $heights[] = $this->series['altitude'][$i] - $this->ground[$i];
            }
        }
        if ([] === $heights) {
            return null;
        }
        if (array_sum($heights) / \count($heights) < self::WAVE_HEIGHT) {
            return false;
        }
        foreach ($this->slopes['cells'] ?? [] as [$latitude, $longitude]) {
            for ($i = $phase->start; $i <= $phase->end; $i += 10) {
                if (Geo::distance($latitude, $longitude, $this->series['latitude'][$i], $this->series['longitude'][$i]) < self::SLOPE_REACH) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Average climb in the thermals by band of altitude (CLIMB_BAND metres), lowest first: where the
     * thermals worked best.
     *
     * @return list<array{bottom: int, climb: float, seconds: int}>
     */
    private function climbByAltitude(): array
    {
        $bands = [];
        $climb = $this->climb ?? $this->series['altitude'];
        foreach ($this->phases as $phase) {
            if (Phase::THERMAL !== $phase->type) {
                continue;
            }
            for ($i = $phase->start; $i < $phase->end; ++$i) {
                $band = (int) floor($this->series['altitude'][$i] / self::CLIMB_BAND) * self::CLIMB_BAND;
                $bands[$band] ??= [0.0, 0];
                $bands[$band][0] += $climb[$i + 1] - $climb[$i];
                ++$bands[$band][1];
            }
        }
        ksort($bands);
        $rows = [];
        foreach ($bands as $bottom => [$gain, $seconds]) {
            // A band crossed in a few seconds says nothing
            if ($seconds >= self::CLIMB_BAND_MIN_TIME) {
                $rows[] = ['bottom' => $bottom, 'climb' => round($gain / $seconds, 2), 'seconds' => $seconds];
            }
        }

        return $rows;
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
            $distance = $task->leg($i + 1);
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
                'angle' => $point->angle,
                // Altitudes of the gate of a 180° start or finish, as Condor shows it
                'minAltitude' => null !== $point->minAltitude ? round($point->minAltitude) : null,
                'maxAltitude' => null !== $point->maxAltitude ? round($point->maxAltitude) : null,
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
