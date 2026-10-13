<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Training\PilotPath;
use App\Training\StepState;
use App\Training\StepView;
use App\Training\TrainingPath;
use App\Training\TrainingStep;
use Twig\Attribute\AsTwigFunction;

/**
 * The training path of a pilot in templates (dashboard of the member space): the trainers' marks
 * (`training_path()`) and the step of "Mon parcours" to work on (`path_current_step()`).
 */
final readonly class TrainingExtension
{
    public function __construct(
        private TrainingPath $path,
        private PilotPath $pilotPath,
    ) {
    }

    /**
     * @return array{steps: list<array{step: TrainingStep, progress: ?\App\Entity\StepProgress}>, current: ?TrainingStep, validated: int, qualification: ?string}
     */
    #[AsTwigFunction('training_path')]
    public function of(User $pilot): array
    {
        return $this->path->of($pilot);
    }

    /** The step of "Mon parcours" the pilot works on, null when the open steps are all done. */
    #[AsTwigFunction('path_current_step')]
    public function currentStep(User $pilot): ?StepView
    {
        return array_find($this->pilotPath->of($pilot), static fn (StepView $step): bool => StepState::Current === $step->state);
    }
}
