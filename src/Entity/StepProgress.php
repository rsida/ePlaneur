<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StepProgressRepository;
use App\Training\ProgressStatus;
use App\Training\TrainingStep;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Where a pilot stands on one step of the training path, as a trainer marked it: in progress or
 * validated, with a note, a flight that shows it and the day it was validated. Replaces the Excel
 * workbooks of the trainers (doc/project/training.md).
 */
#[ORM\Entity(repositoryClass: StepProgressRepository::class)]
#[ORM\UniqueConstraint(columns: ['pilot_id', 'step'])]
class StepProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $pilot;

    #[ORM\Column(type: Types::SMALLINT, enumType: TrainingStep::class)]
    private TrainingStep $step;

    #[ORM\Column(length: 20, enumType: ProgressStatus::class)]
    private ProgressStatus $status;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /** A flight that shows the step (the pilot's). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Flight $flight = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedOn = null;

    /** The trainer who marked it last. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $pilot, TrainingStep $step, ProgressStatus $status, \DateTimeImmutable $updatedAt)
    {
        $this->pilot = $pilot;
        $this->step = $step;
        $this->status = $status;
        $this->updatedAt = $updatedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPilot(): User
    {
        return $this->pilot;
    }

    public function getStep(): TrainingStep
    {
        return $this->step;
    }

    public function getStatus(): ProgressStatus
    {
        return $this->status;
    }

    public function isValidated(): bool
    {
        return ProgressStatus::Validated === $this->status;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getFlight(): ?Flight
    {
        return $this->flight;
    }

    public function getValidatedOn(): ?\DateTimeImmutable
    {
        return $this->validatedOn;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * A trainer marks the step; a validated step without a date is validated on the day.
     */
    public function mark(ProgressStatus $status, ?string $note, ?Flight $flight, ?\DateTimeImmutable $validatedOn, User $trainer, \DateTimeImmutable $now): void
    {
        $this->status = $status;
        $this->note = null !== $note && '' !== trim($note) ? trim($note) : null;
        $this->flight = $flight;
        $this->validatedOn = ProgressStatus::Validated === $status ? ($validatedOn ?? $now->setTime(0, 0)) : null;
        $this->updatedBy = $trainer;
        $this->updatedAt = $now;
    }
}
