<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;

/**
 * The ghosts of a flight, replayed beside it from the same start with the live gap along the task
 * (FlightComparison: at each 250 m of the task, how far behind or ahead in time):
 *
 * - the best other pilot of the same race (the winner, or the second when the flight won);
 * - the pilot's own best flight on the same flight plan, another day (the fastest task finished).
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
            if ($best !== $flight) {
                return self::against($flight, $best, [
                    'kind' => 'race',
                    'rank' => $rank + 1,
                    'label' => $best->getCompetitionId() ?? $best->getPilot(),
                    'title' => \sprintf('%s, %s de la course', $best->getCompetitionId() ?? $best->getPilot(), 0 === $rank ? 'vainqueur' : ($rank + 1).'e'),
                ]);
            }
        }

        return null;
    }

    /**
     * The pilot's best finished flight on the same flight plan, when it is not this one.
     *
     * @param list<Flight> $own the pilot's flights on the flight plan
     *
     * @return array<string, mixed>|null
     */
    public static function best(Flight $flight, array $own): ?array
    {
        // Another day: not the flight itself, nor the same race uploaded twice
        $finished = array_filter($own, static fn (Flight $other): bool => $other !== $flight
            && (null === $other->getId() || $other->getId() !== $flight->getId())
            && (null === $other->getRaceId() || $other->getRaceId() !== $flight->getRaceId())
            && null !== $other->getTaskSpeed());
        if ([] === $finished) {
            return null;
        }
        usort($finished, static fn (Flight $a, Flight $b): int => $b->getTaskSpeed() <=> $a->getTaskSpeed());
        $best = $finished[0];
        $date = $best->getDate()->format('d/m/Y');

        return self::against($flight, $best, [
            'kind' => 'own',
            'rank' => null,
            'label' => 'Mon meilleur',
            'title' => \sprintf('votre meilleur vol sur cette tâche, le %s (%s km/h)', $date, number_format((float) $best->getTaskSpeed(), 1, ',', ' ')),
        ]);
    }

    /**
     * @param array<string, mixed> $about kind, rank, label and title of the ghost
     *
     * @return array<string, mixed>|null
     */
    private static function against(Flight $flight, Flight $best, array $about): ?array
    {
        $comparison = FlightComparison::of($flight, $best);
        if (null === $comparison) {
            return null;
        }
        $series = $best->getAnalysis()['series'];
        $round = static fn (array $values, int $precision): array => array_map(static fn (float $value): float => round($value, $precision), $values);

        return $about + [
            'id' => $best->getId(),
            'pilot' => $about['label'],
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
}
