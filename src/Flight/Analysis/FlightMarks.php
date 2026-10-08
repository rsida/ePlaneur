<?php

declare(strict_types=1);

namespace App\Flight\Analysis;

use App\Flight\Geo\Geo;

/**
 * The marks of a flight that badges are about (FlightAnalysis::toArray()): duration between take-off
 * and landing, height gained from a low point, farthest distance from the take-off, distance flown.
 */
final readonly class FlightMarks
{
    private function __construct(
        public int $duration,
        public float $gain,
        public float $farthest,
        public float $distance,
    ) {
    }

    /**
     * @param array<string, mixed> $analysis
     */
    public static function of(array $analysis): self
    {
        $series = $analysis['series'];
        $statistics = $analysis['statistics'];
        $from = (int) ($statistics['takeoff'] ?? 0);
        $to = (int) ($statistics['landing'] ?? \count($series['time']) - 1);
        $low = \INF;
        $gain = 0.0;
        $farthest = 0.0;
        for ($i = $from; $i <= $to; ++$i) {
            $low = min($low, $series['altitude'][$i]);
            $gain = max($gain, $series['altitude'][$i] - $low);
            if (0 === $i % 10) {
                $farthest = max($farthest, Geo::distance($series['latitude'][$from], $series['longitude'][$from], $series['latitude'][$i], $series['longitude'][$i]));
            }
        }

        return new self((int) $statistics['duration'], $gain, $farthest, (float) ($statistics['distance'] ?? 0));
    }
}
