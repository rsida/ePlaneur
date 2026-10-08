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

    public static function statistic(Flight $flight, string $name): ?float
    {
        $value = $flight->getAnalysis()['statistics'][$name] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
