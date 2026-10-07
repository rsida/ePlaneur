<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MediaRepository;
use App\Security\RestrictedContentInterface;
use App\Security\Visibility;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Uploaded file (image, PDF, text...). The file lives in var/uploads, outside the public directory,
 * and is served by MediaController after a CONTENT_VIEW check, so committee documents stay private.
 */
#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media implements RestrictedContentInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Alternative text for images; empty for decorative pictures. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    /** Author or source of the file, shown in captions. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $credit = null;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    /** Number of pages of a PDF. */
    #[ORM\Column(nullable: true)]
    private ?int $pages = null;

    #[ORM\Column(length: 20, enumType: Visibility::class, options: ['default' => 'public'])]
    private Visibility $visibility = Visibility::Public;

    /**
     * For readers outside its audience: announced (listed with a padlock, its address explains it is
     * reserved) or private (true = announced; false = absent from every list, its address not found).
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $announced = true;

    /** @var Collection<int, Group> */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'media_allowed_group')]
    private Collection $allowedGroups;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    /** Address of the file on the WordPress site it was imported from (app:import-wordpress). */
    #[ORM\Column(length: 500, nullable: true, unique: true)]
    private ?string $sourceUrl = null;

    public function __construct(
        /** Path relative to the uploads directory, e.g. 2026/10/3f2a...c1.png */
        #[ORM\Column(length: 255, unique: true)]
        private string $path,
        /** File name as uploaded, used for downloads */
        #[ORM\Column(length: 255)]
        private string $originalName,
        #[ORM\Column(length: 100)]
        private string $mimeType,
        #[ORM\Column]
        private int $size,
    ) {
        $this->allowedGroups = new ArrayCollection();
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    public function isPdf(): bool
    {
        return 'application/pdf' === $this->mimeType;
    }

    /** Short uppercase format label: PDF, PNG, TXT... */
    public function getFormat(): string
    {
        $extension = pathinfo($this->originalName, \PATHINFO_EXTENSION);

        return '' !== $extension ? mb_strtoupper($extension) : 'FICHIER';
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getCredit(): ?string
    {
        return $this->credit;
    }

    public function setCredit(?string $credit): static
    {
        $this->credit = $credit;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setDimensions(?int $width, ?int $height): static
    {
        $this->width = $width;
        $this->height = $height;

        return $this;
    }

    public function getPages(): ?int
    {
        return $this->pages;
    }

    public function setPages(?int $pages): static
    {
        $this->pages = $pages;

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

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function isAnnounced(): bool
    {
        return $this->announced;
    }

    public function setAnnounced(bool $announced): static
    {
        $this->announced = $announced;

        return $this;
    }

    public function __toString(): string
    {
        return $this->originalName;
    }
}
