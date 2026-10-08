<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MembershipSeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A membership season: a calendar year (memberships end on 31 December, renewal at year end), its
 * membership types and prices, and whether requests are open.
 */
#[ORM\Entity(repositoryClass: MembershipSeasonRepository::class)]
#[UniqueEntity('year', message: 'Cette saison existe déjà.')]
class MembershipSeason
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(unique: true)]
    #[Assert\Range(min: 2020, max: 2100)]
    private int $year;

    /** Members may send requests for this season. */
    #[ORM\Column]
    private bool $open = false;

    /** How to pay when online payment is not available (transfer details...), shown after a request. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $paymentInstructions = null;

    /** @var Collection<int, MembershipType> */
    #[ORM\OneToMany(targetEntity: MembershipType::class, mappedBy: 'season', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Assert\Valid]
    private Collection $types;

    public function __construct(int $year)
    {
        $this->year = $year;
        $this->types = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(?int $year): static
    {
        $this->year = (int) $year;

        return $this;
    }

    public function getLabel(): string
    {
        return 'Saison '.$this->year;
    }

    public function getEndsOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable($this->year.'-12-31');
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

    public function getPaymentInstructions(): ?string
    {
        return $this->paymentInstructions;
    }

    public function setPaymentInstructions(?string $paymentInstructions): static
    {
        $this->paymentInstructions = null !== $paymentInstructions && '' !== trim($paymentInstructions) ? trim($paymentInstructions) : null;

        return $this;
    }

    /**
     * @return Collection<int, MembershipType>
     */
    public function getTypes(): Collection
    {
        return $this->types;
    }

    public function addType(MembershipType $type): static
    {
        if (!$this->types->contains($type)) {
            $this->types->add($type);
            $type->setSeason($this);
        }

        return $this;
    }

    public function removeType(MembershipType $type): static
    {
        $this->types->removeElement($type);

        return $this;
    }
}
