<?php

declare(strict_types=1);

namespace App\Flight\Club;

use App\Entity\Flight;
use App\Entity\User;

/**
 * Records of the club from the uploaded flights: the fastest finish of each task (flight plan), and
 * per landscape the longest distance flown, the longest flight and the highest altitude; and the
 * ranking of a season (calendar year) by kilometres of finished tasks, without the pilots who chose
 * not to appear in it.
 */
final class ClubRecords
{
    /**
     * Best task speed of each flight plan, the most flown first.
     *
     * @param list<Flight> $flights
     *
     * @return list<array{plan: string, best: Flight, count: int}>
     */
    public static function byTask(array $flights): array
    {
        $tasks = [];
        foreach ($flights as $flight) {
            $plan = $flight->getFlightPlan();
            if (null === $plan) {
                continue;
            }
            $tasks[$plan] ??= ['plan' => $plan, 'best' => null, 'count' => 0];
            ++$tasks[$plan]['count'];
            if (null !== $flight->getTaskSpeed() && $flight->getTaskSpeed() > ($tasks[$plan]['best']?->getTaskSpeed() ?? 0)) {
                $tasks[$plan]['best'] = $flight;
            }
        }
        $tasks = array_values(array_filter($tasks, static fn (array $task): bool => null !== $task['best']));
        usort($tasks, static fn (array $a, array $b): int => [$b['count'], $a['plan']] <=> [$a['count'], $b['plan']]);

        /* @var list<array{plan: string, best: Flight, count: int}> */
        return $tasks;
    }

    /**
     * Per landscape: the flights with the longest distance, the longest duration, the highest altitude.
     *
     * @param list<Flight> $flights
     *
     * @return list<array{landscape: string, distance: Flight, duration: Flight, altitude: Flight, altitudeMax: float}>
     */
    public static function byLandscape(array $flights): array
    {
        $landscapes = [];
        foreach ($flights as $flight) {
            $name = $flight->getLandscape() ?? $flight->getSimulator();
            $altitude = (float) ($flight->getAnalysis()['statistics']['altitudeMax'] ?? 0);
            $record = $landscapes[$name] ?? ['landscape' => $name, 'distance' => $flight, 'duration' => $flight, 'altitude' => $flight, 'altitudeMax' => $altitude];
            if ($flight->getDistance() > $record['distance']->getDistance()) {
                $record['distance'] = $flight;
            }
            if ($flight->getDuration() > $record['duration']->getDuration()) {
                $record['duration'] = $flight;
            }
            if ($altitude > $record['altitudeMax']) {
                $record['altitude'] = $flight;
                $record['altitudeMax'] = $altitude;
            }
            $landscapes[$name] = $record;
        }
        ksort($landscapes);

        return array_values($landscapes);
    }

    /**
     * Pilots (accounts) of a season by kilometres of finished tasks, then flights.
     *
     * @param list<Flight> $flights
     *
     * @return list<array{pilot: User, tasks: int, kilometres: float, flights: int, hours: int, bestSpeed: ?float}>
     */
    public static function season(array $flights, int $year): array
    {
        $pilots = [];
        foreach ($flights as $flight) {
            $owner = $flight->getOwner();
            // Pilots who chose not to appear in the ranking ("Confidentialité et données")
            if (null === $owner || !$owner->isInRanking() || (int) $flight->getDate()->format('Y') !== $year) {
                continue;
            }
            $id = spl_object_id($owner);
            $pilots[$id] ??= ['pilot' => $owner, 'tasks' => 0, 'kilometres' => 0.0, 'flights' => 0, 'hours' => 0, 'bestSpeed' => null];
            ++$pilots[$id]['flights'];
            $pilots[$id]['hours'] += $flight->getDuration();
            if (null !== $flight->getTaskSpeed()) {
                ++$pilots[$id]['tasks'];
                $pilots[$id]['kilometres'] += (float) ($flight->getAnalysis()['task']['distance'] ?? 0) / 1000;
                $pilots[$id]['bestSpeed'] = max($pilots[$id]['bestSpeed'] ?? 0.0, $flight->getTaskSpeed());
            }
        }
        $ranking = array_values($pilots);
        usort($ranking, static fn (array $a, array $b): int => [$b['kilometres'], $b['flights']] <=> [$a['kilometres'], $a['flights']]);

        return $ranking;
    }

    /**
     * Years with flights, the latest first.
     *
     * @param list<Flight> $flights
     *
     * @return list<int>
     */
    public static function seasons(array $flights): array
    {
        $years = array_values(array_unique(array_map(static fn (Flight $flight): int => (int) $flight->getDate()->format('Y'), $flights)));
        rsort($years);

        return $years;
    }
}
