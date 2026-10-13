<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PathCompletionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An item of "Mon parcours" a pilot did: a reading or an exercise they said done, or a quiz with the
 * best score of their attempts. Exercises on a flight plan and trainer validations are read from the
 * flights and the training path, not stored here.
 */
#[ORM\Entity(repositoryClass: PathCompletionRepository::class)]
#[ORM\UniqueConstraint(columns: ['pilot_id', 'item_id'])]
class PathCompletion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $pilot;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PathItem $item;

    /** Best score of a quiz, in percent. */
    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\Column]
    private int $attempts = 0;

    /** Last time the pilot did it. */
    #[ORM\Column]
    private \DateTimeImmutable $doneAt;

    public function __construct(User $pilot, PathItem $item, \DateTimeImmutable $doneAt)
    {
        $this->pilot = $pilot;
        $this->item = $item;
        $this->doneAt = $doneAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPilot(): User
    {
        return $this->pilot;
    }

    public function getItem(): PathItem
    {
        return $this->item;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getDoneAt(): \DateTimeImmutable
    {
        return $this->doneAt;
    }

    /** One more attempt at a quiz: keeps the best score. */
    public function attempt(int $score, \DateTimeImmutable $now): void
    {
        $this->score = max($this->score ?? 0, $score);
        ++$this->attempts;
        $this->doneAt = $now;
    }

    public function isDone(): bool
    {
        $quiz = $this->item->getQuiz();

        return null === $quiz || ($this->score ?? 0) >= $quiz->getPassMark();
    }
}
