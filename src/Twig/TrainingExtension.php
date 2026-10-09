<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Training\TrainingPath;
use App\Training\TrainingStep;
use Twig\Attribute\AsTwigFunction;

/**
 * The training path of a pilot in templates (dashboard of the member space).
 */
final readonly class TrainingExtension
{
    public function __construct(
        private TrainingPath $path,
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
}
