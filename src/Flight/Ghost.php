<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;

/**
 * The ghost of a flight: the best other pilot of the same race (the winner, or the second when the
 * flight won), replayed beside it from the same start, with the live gap along the task
 * (FlightComparison: at each 250 m of the task, how far behind or ahead in time).
 */
final class Ghost
{
    /**
     * @param list<Flight> $others the other flights of the same race (FlightRepository::findSameAir())
     *
     * @return array<string, mixed>|null
     */
    public static function of(Flight $flight, array $others): ?array
    {
        if ([] === $others) {
            return null;
        }
        $ranked = FlightSession::of([...$others, $flight])->flights;
        foreach ($ranked as $rank => $best) {
            if ($best === $flight) {
                continue;
            }
            $comparison = FlightComparison::of($flight, $best);
            if (null === $comparison) {
                return null;
            }
            $series = $best->getAnalysis()['series'];
            $round = static fn (array $values, int $precision): array => array_map(static fn (float $value): float => round($value, $precision), $values);

            return [
                'id' => $best->getId(),
                'pilot' => $best->getCompetitionId() ?? $best->getPilot(),
                'rank' => $rank + 1,
                // Index of the ghost = index of the flight + offset: both cross the start line together
                'offset' => $comparison->starts[1] - $comparison->starts[0],
                'latitude' => $round($series['latitude'], 6),
                'longitude' => $round($series['longitude'], 6),
                'altitude' => $round($series['altitude'], 1),
                'speed' => $round($series['speed'], 1),
                'bank' => $series['bank'] ?? null,
                'step' => FlightComparison::STEP,
                // Index of the flight at each step of the task, and its gap there (positive: behind)
                'times' => $comparison->times[0],
                'gaps' => $comparison->gaps(),
            ];
        }

        return null;
    }
}
