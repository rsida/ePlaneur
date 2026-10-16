<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SchoolClassRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\String\ByteString;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A class of a teacher (roadmap 11f, doc/teacher-space.md): the pupils who joined it with its link,
 * whose progression the teacher follows. Deleted with the teacher's account; a pupil leaves it when
 * they want, and their account leaves it when deleted.
 */
#[ORM\Entity(repositoryClass: SchoolClassRepository::class)]
class SchoolClass
{
    /** Letters and digits of a joining code, without those read one for another (0/o, 1/l/i). */
    private const string CODE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Donnez un nom à la classe.')]
    #[Assert\Length(max: 100)]
    private string $name;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $school = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $teacher;

    /** Secret part of the joining link, renewed by the teacher when it went too far. */
    #[ORM\Column(length: 12, unique: true)]
    private string $code;

    /** Whether the link still lets pupils join. */
    #[ORM\Column(options: ['default' => true])]
    private bool $open = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'school_class_pupil')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    #[ORM\OrderBy(['displayName' => 'ASC'])]
    private Collection $pupils;

    public function __construct(User $teacher, string $name, \DateTimeImmutable $createdAt)
    {
        $this->teacher = $teacher;
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->pupils = new ArrayCollection();
        $this->renewCode();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSchool(): ?string
    {
        return $this->school;
    }

    public function setSchool(?string $school): static
    {
        $school = trim((string) $school);
        $this->school = '' === $school ? null : $school;

        return $this;
    }

    public function getTeacher(): User
    {
        return $this->teacher;
    }

    public function setTeacher(User $teacher): static
    {
        $this->teacher = $teacher;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    /** A new link: the old one no longer lets anyone join. */
    public function renewCode(): static
    {
        $this->code = ByteString::fromRandom(12, self::CODE_ALPHABET)->toString();

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, User>
     */
    public function getPupils(): Collection
    {
        return $this->pupils;
    }

    public function hasPupil(User $user): bool
    {
        return $this->pupils->contains($user);
    }

    public function addPupil(User $pupil): static
    {
        if (!$this->pupils->contains($pupil)) {
            $this->pupils->add($pupil);
        }

        return $this;
    }

    public function removePupil(User $pupil): static
    {
        $this->pupils->removeElement($pupil);

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
