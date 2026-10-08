<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FlightCommentRepository;
use App\Training\TrainingStep;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A comment on a flight, by its pilot or a trainer: optionally at a moment of the flight (second of
 * the analysis series) and about a step of the training path.
 */
#[ORM\Entity(repositoryClass: FlightCommentRepository::class)]
class FlightComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Flight $flight;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author;

    /** Second of the flight (index in the analysis series) the comment is about. */
    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $at = null;

    #[ORM\Column(nullable: true, enumType: TrainingStep::class)]
    private ?TrainingStep $step = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Écrivez votre commentaire.')]
    #[Assert\Length(max: 2000, maxMessage: 'Le commentaire dépasse {{ limit }} caractères.')]
    private string $body = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Flight $flight, User $author, \DateTimeImmutable $createdAt)
    {
        $this->flight = $flight;
        $this->author = $author;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFlight(): Flight
    {
        return $this->flight;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getAt(): ?int
    {
        return $this->at;
    }

    public function setAt(?int $at): static
    {
        $this->at = $at;

        return $this;
    }

    public function getStep(): ?TrainingStep
    {
        return $this->step;
    }

    public function setStep(?TrainingStep $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(?string $body): static
    {
        $this->body = trim((string) $body);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
