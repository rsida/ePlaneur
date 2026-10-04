<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PostRepository;
use App\Security\RestrictedContentInterface;
use App\Security\Visibility;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * News post ("actualité"). Its content is made of blocks stored as JSON in three zones:
 * the reading column (body), the sidebar under the table of contents (aside) and the full-width
 * band after the body (outro). See App\Content\Block for the block types.
 *
 * A post is public when publishedAt is set and in the past; visibility then restricts readers.
 */
#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Index(name: 'post_published_at_idx', columns: ['published_at'])]
#[UniqueEntity(fields: ['slug'], message: 'Cette adresse est déjà utilisée par un autre article.')]
class Post implements RestrictedContentInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Small label above the title, e.g. "Guide pratique · Premiers vols". */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $kicker = null;

    /** Short tag next to the kicker, e.g. "Débutant". */
    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40)]
    private ?string $badge = null;

    /** Lead paragraph under the title, also used in cards and meta description. */
    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $excerpt = '';

    /** Optional catchphrase shown next to the title (one line per sentence). */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $highlight = null;

    /** Small text under the catchphrase. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $highlightNote = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $cover = null;

    /** Sentence written over the cover picture. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverCaption = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Category $category = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $keywords = [];

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** Shown in the "À la une" selections (home page). */
    #[ORM\Column(options: ['default' => false])]
    private bool $featured = false;

    #[ORM\Column(length: 20, enumType: Visibility::class, options: ['default' => 'public'])]
    private Visibility $visibility = Visibility::Public;

    /** @var Collection<int, Group> */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'post_allowed_group')]
    private Collection $allowedGroups;

    /** @var list<array{type: string, data: array<string, mixed>}> */
    #[ORM\Column(type: 'json')]
    private array $body = [];

    /** @var list<array{type: string, data: array<string, mixed>}> */
    #[ORM\Column(type: 'json')]
    private array $aside = [];

    /** @var list<array{type: string, data: array<string, mixed>}> */
    #[ORM\Column(type: 'json')]
    private array $outro = [];

    public function __construct(
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        private string $title,
        #[ORM\Column(length: 160, unique: true)]
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Lettres minuscules, chiffres et tirets uniquement.')]
        private string $slug,
    ) {
        $this->allowedGroups = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getKicker(): ?string
    {
        return $this->kicker;
    }

    public function setKicker(?string $kicker): static
    {
        $this->kicker = $kicker;

        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function getExcerpt(): string
    {
        return $this->excerpt;
    }

    public function setExcerpt(string $excerpt): static
    {
        $this->excerpt = $excerpt;

        return $this;
    }

    public function getHighlight(): ?string
    {
        return $this->highlight;
    }

    public function setHighlight(?string $highlight): static
    {
        $this->highlight = $highlight;

        return $this;
    }

    public function getHighlightNote(): ?string
    {
        return $this->highlightNote;
    }

    public function setHighlightNote(?string $highlightNote): static
    {
        $this->highlightNote = $highlightNote;

        return $this;
    }

    public function getCover(): ?Media
    {
        return $this->cover;
    }

    public function setCover(?Media $cover): static
    {
        $this->cover = $cover;

        return $this;
    }

    public function getCoverCaption(): ?string
    {
        return $this->coverCaption;
    }

    public function setCoverCaption(?string $coverCaption): static
    {
        $this->coverCaption = $coverCaption;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getKeywords(): array
    {
        return $this->keywords;
    }

    /**
     * @param list<string> $keywords
     */
    public function setKeywords(array $keywords): static
    {
        $this->keywords = array_values(array_unique(array_filter(array_map('trim', $keywords))));

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    public function isPublished(?\DateTimeImmutable $now = null): bool
    {
        return null !== $this->publishedAt && $this->publishedAt <= ($now ?? new \DateTimeImmutable());
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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

    /** True when the post was edited noticeably after its publication (another day). */
    public function wasUpdatedAfterPublication(): bool
    {
        return null !== $this->publishedAt && $this->updatedAt->format('Y-m-d') > $this->publishedAt->format('Y-m-d');
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

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

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * @param array<int, array{type: string, data: array<string, mixed>}> $body re-indexed as a list
     */
    public function setBody(array $body): static
    {
        $this->body = array_values($body);

        return $this;
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function getAside(): array
    {
        return $this->aside;
    }

    /**
     * @param array<int, array{type: string, data: array<string, mixed>}> $aside re-indexed as a list
     */
    public function setAside(array $aside): static
    {
        $this->aside = array_values($aside);

        return $this;
    }

    /**
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function getOutro(): array
    {
        return $this->outro;
    }

    /**
     * @param array<int, array{type: string, data: array<string, mixed>}> $outro re-indexed as a list
     */
    public function setOutro(array $outro): static
    {
        $this->outro = array_values($outro);

        return $this;
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
