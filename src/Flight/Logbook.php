<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\Flight;
use App\Entity\User;
use App\Flight\Advice\Advice;
use App\Flight\Advice\FlightAdvisor;
use App\Flight\Club\Badge;
use App\Repository\FlightRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The logbook of a pilot (member space and trainers' view): flights from the oldest, totals, the
 * values of each flight for the trend charts, the advice that comes back most, badges.
 */
final readonly class Logbook
{
    public function __construct(
        private FlightRepository $flights,
        private FlightAdvisor $advisor,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return array{flights: list<Flight>, points: list<array<string, mixed>>, tips: list<array{title: string, step: mixed, count: int}>, hours: int, distance: int, tasks: int, badges: list<array{badge: Badge, flight: ?Flight}>}
     */
    public function of(User $pilot): array
    {
        $logbook = $this->flights->findLogbook($pilot);
        $points = [];
        $tips = [];
        foreach ($logbook as $flight) {
            $statistics = $flight->getAnalysis()['statistics'];
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
        ];
    }
}
