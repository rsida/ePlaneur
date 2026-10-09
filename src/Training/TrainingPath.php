<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\StepProgress;
use App\Entity\User;
use App\Repository\StepProgressRepository;

/**
 * The training path of a pilot: the eight steps with their progress, the current step and the
 * highest internal qualification reached.
 */
final readonly class TrainingPath
{
    public function __construct(
        private StepProgressRepository $progress,
    ) {
    }

    /**
     * @return array{steps: list<array{step: TrainingStep, progress: ?StepProgress}>, current: ?TrainingStep, validated: int, qualification: ?string}
     */
    public function of(User $pilot): array
    {
        $byStep = $this->progress->findByPilot($pilot);
        $steps = [];
        $current = null;
        $validated = 0;
        $qualification = null;
        foreach (TrainingStep::cases() as $step) {
            $progress = $byStep[$step->value] ?? null;
            $steps[] = ['step' => $step, 'progress' => $progress];
            if (null !== $progress && $progress->isValidated()) {
                ++$validated;
                $qualification = $step->qualification() ?? $qualification;
            } elseif (null === $current) {
                // The first step not validated yet
                $current = $step;
            }
        }

        return ['steps' => $steps, 'current' => $current, 'validated' => $validated, 'qualification' => $qualification];
    }
}
