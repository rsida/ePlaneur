<?php

declare(strict_types=1);

namespace App\Activity;

/**
 * The activity of the club in a month, or in a period (ActivityMonth::sum()).
 */
final readonly class ActivityMonth
{
    /** Indicators by name, with their French label: the columns of the table and of the export. */
    public const array LABELS = [
        'accounts' => 'Comptes créés',
        'memberships' => 'Adhésions validées',
        'flights' => 'Vols déposés',
        'hours' => 'Heures de vol',
        'pilots' => 'Pilotes actifs',
        'sessions' => 'Sessions organisées',
        'participations' => 'Participations aux sessions (traces déposées)',
        'steps' => 'Étapes validées',
        'items' => 'Éléments de Mon parcours faits',
        'comments' => 'Commentaires des formateurs',
    ];

    public function __construct(
        public ?\DateTimeImmutable $month,
        public int $accounts = 0,
        public int $memberships = 0,
        public int $flights = 0,
        public int $seconds = 0,
        public int $pilots = 0,
        public int $sessions = 0,
        public int $participations = 0,
        public int $steps = 0,
        public int $items = 0,
        public int $comments = 0,
    ) {
    }

    /**
     * @param array<string, mixed> $counts
     */
    public static function of(\DateTimeImmutable $month, array $counts, int $pilots): self
    {
        $int = static fn (string $name): int => (int) ($counts[$name] ?? 0);

        return new self($month, $int('accounts'), $int('memberships'), $int('flights'), $int('seconds'), $pilots, $int('sessions'), $int('participations'), $int('steps'), $int('items'), $int('comments'));
    }

    /**
     * The months added up; the active pilots of a period are counted once each.
     *
     * @param list<self> $months
     */
    public static function sum(array $months, int $pilots): self
    {
        $total = static fn (string $name): int => array_sum(array_map(static fn (self $month): int => $month->{$name}, $months));

        return new self(null, $total('accounts'), $total('memberships'), $total('flights'), $total('seconds'), $pilots, $total('sessions'), $total('participations'), $total('steps'), $total('items'), $total('comments'));
    }

    /** Flight hours, to the tenth. */
    public function hours(): float
    {
        return round($this->seconds / 3600, 1);
    }

    /**
     * The indicators by name (LABELS), hours included.
     *
     * @return array<string, int|float>
     */
    public function values(): array
    {
        $values = [];
        foreach (array_keys(self::LABELS) as $name) {
            $values[$name] = 'hours' === $name ? $this->hours() : $this->{$name};
        }

        return $values;
    }
}
