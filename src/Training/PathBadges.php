<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\Flight;
use App\Entity\User;
use App\Repository\FlightRepository;

/**
 * The badges of "Mon parcours" of a pilot (PathBadge), counted from their path and their logbook.
 */
final readonly class PathBadges
{
    /** Places of a ranking that count for the podium badge. */
    private const int PODIUM = 3;

    public function __construct(
        private PilotPath $path,
        private FlightRepository $flights,
        private ExerciseCheck $check,
    ) {
    }

    /**
     * Every badge with what the pilot counted; a visitor gets them all at zero.
     *
     * @param list<StepView>|null $steps the pilot's path when already built
     *
     * @return list<BadgeView>
     */
    public function of(?User $pilot, ?array $steps = null): array
    {
        if (null === $pilot) {
            return array_map(static fn (PathBadge $badge): BadgeView => new BadgeView($badge, 0), PathBadge::cases());
        }
        $steps ??= $this->path->of($pilot);
        $items = array_merge(...array_map(static fn (StepView $step): array => $step->items, $steps));
        $logbook = $this->flights->findLogbook($pilot);

        return array_map(fn (PathBadge $badge): BadgeView => new BadgeView($badge, match ($badge) {
            PathBadge::Steps => \count(array_filter($steps, static fn (StepView $step): bool => StepState::Done === $step->state)),
            PathBadge::Exercises => $this->countDone($items, PathItemType::Exercise),
            PathBadge::Quizzes => $this->countDone($items, PathItemType::Quiz),
            PathBadge::Landings => \count(array_filter($logbook, fn (Flight $flight): bool => true === $this->check->measure(ExerciseCriterion::LandedOnAirfield, $flight))),
            PathBadge::CleanFlights => \count(array_filter($logbook, fn (Flight $flight): bool => 0.0 === $this->check->measure(ExerciseCriterion::Penalties, $flight))),
            PathBadge::NetworkFlights => \count(array_filter($logbook, static fn (Flight $flight): bool => null !== $flight->getSession())),
            PathBadge::Weeks => \count(array_unique(array_map(static fn (Flight $flight): string => $flight->getDate()->format('o-W'), $logbook))),
            PathBadge::Podiums => $this->podiums($pilot, $steps),
        }), PathBadge::cases());
    }

    /**
     * @param list<ItemView> $items
     */
    private function countDone(array $items, PathItemType $type): int
    {
        return \count(array_filter($items, static fn (ItemView $view): bool => $view->done && $type === $view->item->getType()));
    }

    /**
     * Exercises of the open steps whose ranking is shown (enough pilots) with the pilot in the first
     * places.
     *
     * @param list<StepView> $steps
     */
    private function podiums(User $pilot, array $steps): int
    {
        $podiums = 0;
        foreach ($steps as $step) {
            if (StepState::Closed === $step->state) {
                continue;
            }
            foreach ($step->items as $view) {
                if (PathItemType::Exercise !== $view->item->getType() || [] === $view->passed()) {
                    continue;
                }
                $ranking = $this->path->ranking($view->item);
                $rank = array_find_key($ranking, static fn (ExerciseResult $result): bool => $result->flight->getOwner() === $pilot);
                if (\count($ranking) >= $view->item->getRankingFrom() && null !== $rank && $rank < self::PODIUM) {
                    ++$podiums;
                }
            }
        }

        return $podiums;
    }
}
