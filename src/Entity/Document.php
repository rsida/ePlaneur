<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocumentRepository;
use App\Security\RestrictedContentInterface;
use App\Security\Visibility;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Official document of the association (statutes, internal rules, decisions...): a file with its
 * version and dated mentions (adoption, declaration, publication...). Its visibility is copied onto its file (see DocumentAccessListener),
 * so a restricted document cannot be downloaded through the media URL either.
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
class Document implements RestrictedContentInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?DocumentCategory $category = null;

    /** Version label, e.g. "V13". */
    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $version = null;

    /**
     * Dated mentions, one per line: "Adoptés le 15/12/2025", "Déclaration : 26/01/2026"...
     *
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $details = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(length: 20, enumType: Visibility::class, options: ['default' => 'public'])]
    private Visibility $visibility = Visibility::Public;

    /** @var Collection<int, Group> */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'document_allowed_group')]
    private Collection $allowedGroups;

    public function __construct(
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        private string $title,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
        private Media $file,
    ) {
        $this->allowedGroups = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getFile(): Media
    {
        return $this->file;
    }

    public function setFile(Media $file): static
    {
        $this->file = $file;

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

    public function getCategory(): ?DocumentCategory
    {
        return $this->category;
    }

    public function setCategory(?DocumentCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(?string $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * @param list<string> $details
     */
    public function setDetails(array $details): static
    {
        $this->details = array_values(array_filter(array_map('trim', $details)));

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getVisibility(): Visibility
    {
        return $this->visibility;
    }

    public function setVisibility(Visibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * @return Collection<int, Group>
     */
    public function getAllowedGroups(): Collection
    {
        return $this->allowedGroups;
    }

    public function addAllowedGroup(Group $group): static
    {
        if (!$this->allowedGroups->contains($group)) {
            $this->allowedGroups->add($group);
        }

        return $this;
    }

    public function removeAllowedGroup(Group $group): static
    {
        $this->allowedGroups->removeElement($group);

        return $this;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
