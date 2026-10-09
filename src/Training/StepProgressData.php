<?php

declare(strict_types=1);

namespace App\Training;

use App\Entity\Flight;
use App\Entity\StepProgress;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a trainer sets on a step of a pilot (StepProgressType); no status: the step is not started.
 */
final class StepProgressData
{
    public ?ProgressStatus $status = null;

    #[Assert\Length(max: 2000)]
    public ?string $note = null;

    public ?Flight $flight = null;

    public ?\DateTimeImmutable $validatedOn = null;

    public static function of(?StepProgress $progress): self
    {
        $data = new self();
        if (null !== $progress) {
            $data->status = $progress->getStatus();
            $data->note = $progress->getNote();
            $data->flight = $progress->getFlight();
            $data->validatedOn = $progress->getValidatedOn();
        }

        return $data;
    }
}
