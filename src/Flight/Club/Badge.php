<?php

declare(strict_types=1);

namespace App\Flight\Club;

use App\Entity\Flight;
use App\Flight\Analysis\FlightMarks;

/**
 * Steps of a pilot's flying, earned by a flight (computed from the flights, nothing stored): kept
 * few and tied to what the training path values (distance, endurance, climbing, finishing a task).
 */
enum Badge: string
{
    case FirstTask = 'first_task';
    case Distance100 = 'distance_100';
    case Distance300 = 'distance_300';
    case Distance500 = 'distance_500';
    case Hours3 = 'hours_3';
    case Hours5 = 'hours_5';
    case Gain1000 = 'gain_1000';
    case SilverMarks = 'silver_marks';

    public function label(): string
    {
        return match ($this) {
            self::FirstTask => 'Premier circuit terminé',
            self::Distance100 => '100 km',
            self::Distance300 => '300 km',
            self::Distance500 => '500 km',
            self::Hours3 => 'Vol de 3 h',
            self::Hours5 => 'Vol de 5 h',
            self::Gain1000 => '1 000 m de gain',
            self::SilverMarks => 'Repères de l’insigne d’argent',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FirstTask => 'Un circuit bouclé, du départ à l’arrivée.',
            self::Distance100, self::Distance300, self::Distance500 => \sprintf('%s parcourus en un vol.', $this->label()),
            self::Hours3, self::Hours5 => 'Entre le décollage et l’atterrissage.',
            self::Gain1000 => 'Depuis un point bas du vol.',
            self::SilverMarks => '1 h de vol, 1 000 m de gain et 50 km du départ dans le même vol (indicatif).',
        };
    }

    public function isEarnedBy(Flight $flight, FlightMarks $marks): bool
    {
        return match ($this) {
            self::FirstTask => null !== $flight->getTaskSpeed(),
            self::Distance100 => $marks->distance >= 100000,
            self::Distance300 => $marks->distance >= 300000,
            self::Distance500 => $marks->distance >= 500000,
            self::Hours3 => $marks->duration >= 3 * 3600,
            self::Hours5 => $marks->duration >= 5 * 3600,
            self::Gain1000 => $marks->gain >= 1000,
            self::SilverMarks => $marks->duration >= 3600 && $marks->gain >= 1000 && $marks->farthest >= 50000,
        };
    }

    /**
     * Every badge with the first flight (by date) that earned it, null when not earned yet.
     *
     * @param list<Flight> $flights oldest first
     *
     * @return list<array{badge: self, flight: ?Flight}>
     */
    public static function earned(array $flights): array
    {
        $first = [];
        foreach ($flights as $flight) {
            $marks = FlightMarks::of($flight->getAnalysis());
            foreach (self::cases() as $badge) {
                if (!isset($first[$badge->value]) && $badge->isEarnedBy($flight, $marks)) {
                    $first[$badge->value] = $flight;
                }
            }
        }

        return array_map(static fn (self $badge): array => ['badge' => $badge, 'flight' => $first[$badge->value] ?? null], self::cases());
    }
}
