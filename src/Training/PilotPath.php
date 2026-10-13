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
        $finished = null !== $pilot ? $this->finishedTasks($pilot) : [];

        $views = [];
        $before = true; // every step before is done
        $closed = false;
        foreach ($this->steps->findPath() as $step) {
            $mark = $progress[$step->getNumber()] ?? null;
            $items = [];
            foreach ($step->getItems() as $item) {
                $items[] = $this->item($item, $completions[(int) $item->getId()] ?? null, $finished, null !== $mark && $mark->isValidated());
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
     * The best pilots of an exercise on a flight plan: the fastest finished task of each pilot who
     * accepts to appear in rankings, the fastest first.
     *
     * @return list<Flight>
     */
    public function ranking(PathItem $item): array
    {
        if (null === $item->getFlightPlan()) {
            return [];
        }
        $best = [];
        foreach ($this->flights->findOnFlightPlan($item->getFlightPlan()) as $flight) {
            $owner = $flight->getOwner();
            if (null === $owner || null === $flight->getTaskSpeed() || !$owner->isInRanking()) {
                continue;
            }
            $id = (int) $owner->getId();
            if (!isset($best[$id]) || $flight->getTaskSpeed() > $best[$id]->getTaskSpeed()) {
                $best[$id] = $flight;
            }
        }
        $ranking = array_values($best);
        usort($ranking, static fn (Flight $a, Flight $b): int => $b->getTaskSpeed() <=> $a->getTaskSpeed());

        return $ranking;
    }

    /**
     * @param array<string, list<Flight>> $finished
     */
    private function item(PathItem $item, ?PathCompletion $completion, array $finished, bool $validated): ItemView
    {
        return match (true) {
            PathItemType::Validation === $item->getType() => new ItemView($item, $validated),
            null !== $item->getFlightPlan() && PathItemType::Exercise === $item->getType() => new ItemView($item, [] !== ($finished[$item->getFlightPlan()] ?? []), null, $finished[$item->getFlightPlan()] ?? []),
            default => new ItemView($item, null !== $completion && $completion->isDone(), $completion),
        };
    }

    /**
     * The pilot's finished tasks by flight plan code, the fastest first.
     *
     * @return array<string, list<Flight>>
     */
    private function finishedTasks(User $pilot): array
    {
        $byPlan = [];
        foreach ($this->flights->findLogbook($pilot) as $flight) {
            $code = FlightPlan::codeIn($flight->getFlightPlan());
            if (null !== $code && null !== $flight->getTaskSpeed()) {
                $byPlan[$code][] = $flight;
            }
        }
        foreach ($byPlan as &$flights) {
            usort($flights, static fn (Flight $a, Flight $b): int => $b->getTaskSpeed() <=> $a->getTaskSpeed());
        }

        return $byPlan;
    }
}
