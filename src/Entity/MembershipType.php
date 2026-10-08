<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A kind of membership of a season and its price: "Moins de 25 ans" 10 €, "Adulte" 25 €...
 */
#[ORM\Entity]
class MembershipType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Set when added to a season (MembershipSeason::addType()), nullable for the forms. */
    #[ORM\ManyToOne(inversedBy: 'types')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?MembershipSeason $season = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Donnez un nom à ce type d’adhésion.')]
    #[Assert\Length(max: 80)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Price in cents. */
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $price = 0;

    /** Highest age on 1 January of the season, for a reduced rate ("Moins de 25 ans": 24). */
    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $maxAge = null;

    #[ORM\Column]
    private int $position = 0;

    public function __toString(): string
    {
        return \sprintf('%s (%s €)', $this->name, number_format($this->price / 100, $this->price % 100 > 0 ? 2 : 0, ',', ' '));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeason(): ?MembershipSeason
    {
        return $this->season;
    }

    public function setSeason(?MembershipSeason $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = (int) $price;

        return $this;
    }

    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }

    public function setMaxAge(?int $maxAge): static
    {
        $this->maxAge = $maxAge;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = (int) $position;

        return $this;
    }

    /** Whether someone born on that day may take this membership (age on 1 January of the season). */
    public function allowsBirthDate(?\DateTimeImmutable $birthDate): bool
    {
        if (null === $this->maxAge || null === $this->season || null === $birthDate) {
            return true;
        }

        return $birthDate->diff(new \DateTimeImmutable($this->season->getYear().'-01-01'))->y <= $this->maxAge;
    }
}
