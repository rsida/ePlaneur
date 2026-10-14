<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\Flight;
use App\Entity\FlightPlan;
use App\Entity\PathCompletion;
use App\Entity\PathItem;
use App\Entity\User;
use App\Repository\FlightRepository;
use App\Repository\PathCompletionRepository;
use App\Repository\PathStepRepository;
use App\Repository\StepProgressRepository;

/**
 * "Mon parcours" of a pilot: the eight steps one after the other. A step is done when every item of
 * it is done, or when a trainer validated it in the training path; the first step not done is the
 * current one, the open steps after it wait for it, and the path stops at the first step not open.
 */
final readonly class PilotPath
{
    public function __construct(
        private PathStepRepository $steps,
        private PathCompletionRepository $completions,
        private StepProgressRepository $progress,
        private FlightRepository $flights,
        private ExerciseCheck $check,
    ) {
    }

    /**
     * The path of a pilot, or the path as a visitor sees it (nothing done).
     *
     * @return list<StepView>
     */
    public function of(?User $pilot): array
    {
        $completions = null !== $pilot ? $this->completions->findByPilot($pilot) : [];
        $progress = null !== $pilot ? $this->progress->findByPilot($pilot) : [];
        $flights = null !== $pilot ? $this->flightsByPlan($pilot) : [];

        $views = [];
        $before = true; // every step before is done
        $closed = false;
        foreach ($this->steps->findPath() as $step) {
            $mark = $progress[$step->getNumber()] ?? null;
            $items = [];
            foreach ($step->getItems() as $item) {
                $items[] = $this->item($item, $completions[(int) $item->getId()] ?? null, $flights, null !== $mark && $mark->isValidated());
            }
            $complete = (null !== $mark && $mark->isValidated())
                || ([] !== $items && array_all($items, static fn (ItemView $view): bool => $view->done));
            $closed = $closed || !$step->isOpen();
            $state = match (true) {
                $closed => StepState::Closed,
                !$before => StepState::Locked,
                $complete => StepState::Done,
                default => StepState::Current,
            };
            $views[] = new StepView($step, $state, $items, $mark);
            $before = $before && $complete;
        }

        return $views;
    }

    /**
     * The best pilots of an exercise on a flight plan: the best flight that passed it of each pilot
     * who accepts to appear in rankings, by the ranking measure of the exercise.
     *
     * @return list<ExerciseResult>
     */
    public function ranking(PathItem $item): array
    {
        if (null === $item->getFlightPlan()) {
            return [];
        }
        $byPilot = [];
        foreach ($this->flights->findOnFlightPlan($item->getFlightPlan()) as $flight) {
            $owner = $flight->getOwner();
            if (null !== $owner && $owner->isInRanking()) {
                $result = $this->check->check($item, $flight);
                if ($result->passed()) {
                    $byPilot[(int) $owner->getId()][] = $result;
                }
            }
        }

        return ExerciseRanking::sort(array_map(static fn (array $results): ExerciseResult => ExerciseRanking::best($results, $item->getRankBy()) ?? $results[0], $byPilot), $item->getRankBy());
    }

    /**
     * @param array<string, list<Flight>> $flights
     */
    private function item(PathItem $item, ?PathCompletion $completion, array $flights, bool $validated): ItemView
    {
        if (PathItemType::Validation === $item->getType()) {
            return new ItemView($item, $validated);
        }
        if (PathItemType::Exercise === $item->getType() && null !== $item->getFlightPlan()) {
            $results = array_map(fn (Flight $flight): ExerciseResult => $this->check->check($item, $flight), $flights[$item->getFlightPlan()] ?? []);

            return new ItemView($item, array_any($results, static fn (ExerciseResult $result): bool => $result->passed()), null, $results);
        }

        return new ItemView($item, null !== $completion && $completion->isDone(), $completion);
    }

    /**
     * The pilot's flights by flight plan code, the latest first.
     *
     * @return array<string, list<Flight>>
     */
    private function flightsByPlan(User $pilot): array
    {
        $byPlan = [];
        foreach (array_reverse($this->flights->findLogbook($pilot)) as $flight) {
            $code = FlightPlan::codeIn($flight->getFlightPlan());
            if (null !== $code) {
                $byPlan[$code][] = $flight;
            }
        }

        return $byPlan;
    }
}
