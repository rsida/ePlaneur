<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PathStepRepository;
use App\Training\TrainingStep;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A step of "Mon parcours" as the club configures it: its introduction, whether it is open yet, and
 * the items that complete it. The eight steps exist from the start (created by their migration);
 * the path stops at the first step that is not open.
 */
#[ORM\Entity(repositoryClass: PathStepRepository::class)]
class PathStep
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::SMALLINT, unique: true, enumType: TrainingStep::class)]
    private TrainingStep $step;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $intro = null;

    #[ORM\Column]
    private bool $open = false;

    /** @var Collection<int, PathItem> */
    #[ORM\OneToMany(targetEntity: PathItem::class, mappedBy: 'step', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $items;

    public function __construct(TrainingStep $step)
    {
        $this->step = $step;
        // The path opens with its first step
        $this->open = TrainingStep::Discover === $step;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStep(): TrainingStep
    {
        return $this->step;
    }

    public function getNumber(): int
    {
        return $this->step->value;
    }

    public function getTitle(): string
    {
        return $this->step->label();
    }

    public function getIntro(): ?string
    {
        return $this->intro;
    }

    public function setIntro(?string $intro): static
    {
        $this->intro = null !== $intro && '' !== trim($intro) ? trim($intro) : null;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function setOpen(bool $open): static
    {
        $this->open = $open;

        return $this;
    }

    /** @return Collection<int, PathItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function getItemCount(): int
    {
        return $this->items->count();
    }

    public function addItem(PathItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setStep($this);
        }

        return $this;
    }

    public function removeItem(PathItem $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }

    public function __toString(): string
    {
        return $this->step->title();
    }
}
