<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;
use App\Entity\User;
use App\Flight\Advice\Advice;
use App\Flight\Advice\FlightAdvisor;
use App\Flight\Club\Badge;
use App\Repository\FlightRepository;
use App\Training\TrainingStep;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The logbook of a pilot (member space and trainers' view): flights from the oldest, totals, the
 * values of each flight for the trend charts, the advice that comes back most, badges, and the
 * level of each skill of the training path on the last flights against the ones before.
 */
final readonly class Logbook
{
    /** Flights averaged for the level of a skill now, and the same number before them. */
    private const int RECENT = 3;

    /** A change under this share of the value is no change. */
    private const float STEADY = 0.05;

    public function __construct(
        private FlightRepository $flights,
        private FlightAdvisor $advisor,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return array{flights: list<Flight>, points: list<array<string, mixed>>, tips: list<array{title: string, step: mixed, count: int}>, hours: int, distance: int, tasks: int, badges: list<array{badge: Badge, flight: ?Flight}>, skills: list<array<string, mixed>>}
     */
    public function of(User $pilot): array
    {
        $logbook = $this->flights->findLogbook($pilot);
        $points = [];
        $tips = [];
        foreach ($logbook as $flight) {
            $statistics = $flight->getStatistics();
            $points[] = [
                'url' => $this->urls->generate('app_flight_show', ['id' => $flight->getId()]),
                'date' => $flight->getDate()->format('d/m/Y'),
                'plan' => $flight->getFlightPlan() ?? $flight->getOriginalName(),
                'taskSpeed' => $flight->getTaskSpeed(),
                'climb' => $statistics['climbAverage'] ?? null,
                'glideRatio' => $statistics['glideRatio'] ?? null,
                'circling' => null !== ($statistics['circlingShare'] ?? null) ? round($statistics['circlingShare'] * 100) : null,
            ];
            foreach ($this->advisor->advise($flight->getAnalysis()) as $advice) {
                if (Advice::TIP === $advice->kind) {
                    $tips[$advice->title] ??= ['title' => $advice->title, 'step' => $advice->step, 'count' => 0];
                    ++$tips[$advice->title]['count'];
                }
            }
        }
        usort($tips, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return [
            'flights' => $logbook,
            'points' => $points,
            'tips' => $tips,
            'hours' => array_sum(array_map(static fn (Flight $flight): int => $flight->getDuration(), $logbook)),
            'distance' => array_sum(array_map(static fn (Flight $flight): int => $flight->getDistance(), $logbook)),
            'tasks' => \count(array_filter($logbook, static fn (Flight $flight): bool => null !== $flight->getTaskSpeed())),
            'badges' => Badge::earned($logbook),
            'skills' => self::skills($logbook),
        ];
    }

    /**
     * Each skill measured on the flights: its value on the last RECENT flights that have it, on the
     * RECENT before them, and whether it got better.
     *
     * @param list<Flight> $flights from the oldest
     *
     * @return list<array{title: string, step: TrainingStep, unit: string, decimals: int, recent: ?float, before: ?float, trend: ?string, count: int}>
     */
    private static function skills(array $flights): array
    {
        $thermals = static fn (array $analysis): array => array_values(array_filter($analysis['phases'] ?? [], static fn (array $phase): bool => 'thermal' === $phase['type']));
        $hours = static fn (array $analysis): float => max(1, $analysis['statistics']['duration'] ?? 0) / 3600;
        $skills = [
            // Title, step, unit, decimals, higher is better, value of a flight
            ['Centrage', TrainingStep::Flaps, 's avant le premier bon tour', 0, false, static function (array $analysis) use ($thermals): ?float {
                $entries = array_filter(array_map(static fn (array $phase): ?int => $phase['entry'] ?? null, $thermals($analysis)), static fn (?int $entry): bool => null !== $entry);

                return [] !== $entries ? array_sum($entries) / \count($entries) : null;
            }],
            ['Montée en thermique', TrainingStep::Flaps, 'm/s', 1, true, static fn (array $analysis): ?float => $analysis['statistics']['climbAverage'] ?? null],
            ['Temps en spirale', TrainingStep::MacCready, '% du vol', 0, false, static fn (array $analysis): ?float => null !== ($analysis['statistics']['circlingShare'] ?? null) ? $analysis['statistics']['circlingShare'] * 100 : null],
            ['Sorties de thermique', TrainingStep::MacCready, 's par heure en haut sous la moyenne', 0, false, static fn (array $analysis): ?float => [] !== $thermals($analysis) ? array_sum(array_map(static fn (array $phase): int => $phase['exit'] ?? 0, $thermals($analysis))) / $hours($analysis) : null],
            ['Vol en dauphin', TrainingStep::MacCready, 'm gagnés par heure sans spiraler', 0, true, static fn (array $analysis): ?float => isset($analysis['crossings']) ? array_sum(array_map(static fn (array $crossing): float => max(0.0, $crossing['gain']), $analysis['crossings'])) / $hours($analysis) : null],
            ['Finesse en transition', TrainingStep::MacCready, '', 0, true, static fn (array $analysis): ?float => $analysis['statistics']['glideRatio'] ?? null],
        ];
        $rows = [];
        foreach ($skills as [$title, $step, $unit, $decimals, $higher, $measure]) {
            $values = array_values(array_filter(array_map(static fn (Flight $flight): mixed => $measure($flight->getAnalysis()), $flights), static fn (mixed $value): bool => is_numeric($value)));
            if ([] === $values) {
                continue;
            }
            $average = static fn (array $list): ?float => [] !== $list ? array_sum($list) / \count($list) : null;
            $recent = $average(\array_slice($values, -self::RECENT));
            // The RECENT values before the recent ones (fewer when there are not enough)
            $count = \count($values);
            $before = $average(\array_slice($values, max(0, $count - 2 * self::RECENT), max(0, min(self::RECENT, $count - self::RECENT))));
            $trend = null;
            if (null !== $recent && null !== $before) {
                $change = $recent - $before;
                $trend = abs($change) <= self::STEADY * max(abs($before), 1e-9) ? 'steady' : (($change > 0) === $higher ? 'better' : 'worse');
            }
            $rows[] = ['title' => $title, 'step' => $step, 'unit' => $unit, 'decimals' => $decimals, 'recent' => $recent, 'before' => $before, 'trend' => $trend, 'count' => $count];
        }

        return $rows;
    }
}
