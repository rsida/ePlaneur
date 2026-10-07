<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PageRepository;
use App\Security\RestrictedContentInterface;
use App\Security\Visibility;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Institutional page ("Statuts", "Présentation du club"...). Pages form a tree that gives their URL
 * (/le-club/textes-officiels/statuts) and breadcrumb; navigation menus are a separate tree
 * (MenuItem). Content is made of blocks, like posts (see App\Content\Block).
 */
#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\UniqueConstraint(name: 'page_parent_slug', columns: ['parent_id', 'slug'])]
#[UniqueEntity(fields: ['parent', 'slug'], message: 'Une page porte déjà cette adresse à cet endroit.', ignoreNull: false)]
class Page implements RestrictedContentInterface
{
    /** Maximum depth of the tree (the current site uses 3 levels, 4 for committee pages). */
    public const int MAX_DEPTH = 4;

    /** First URL segments used by the application: a root page cannot take them. */
    public const array RESERVED_SLUGS = ['actualites', 'connexion', 'deconnexion', 'inscription', 'mot-de-passe-oublie', 'mon-compte', 'media', 'admin'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Deleting a page with children is refused by PageDeletionGuard; the database cascades only for bulk purges. */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Page $parent = null;

    /** @var Collection<int, Page> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC', 'title' => 'ASC'])]
    private Collection $children;

    /** Order among its siblings. */
    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    /** Small label above the title. */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $kicker = null;

    /** Lead paragraph under the title, also used in child page cards and meta description. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $excerpt = null;

    /** Catchphrase next to the title (one line per sentence); inherited from the nearest ancestor when empty. */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $highlight = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $highlightNote = null;

    /** Label of the links to this page in cards ("Découvrir le club"). */
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $linkLabel = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

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
    #[ORM\JoinTable(name: 'page_allowed_group')]
    private Collection $allowedGroups;

    /** @var list<array{type: string, data: array<string, mixed>}> */
    #[ORM\Column(type: 'json')]
    private array $body = [];

    /** @var list<array{type: string, data: array<string, mixed>}> */
    #[ORM\Column(type: 'json')]
    private array $aside = [];

    /** Id of the WordPress post this content was imported from (app:import-wordpress), to update it on a new import. */
    #[ORM\Column(nullable: true, unique: true)]
    private ?int $wordpressId = null;

    public function __construct(
        #[ORM\Column(length: 255)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        private string $title,
        /** URL segment, unique among siblings */
        #[ORM\Column(length: 120)]
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Lettres minuscules, chiffres et tirets uniquement.')]
        private string $slug,
    ) {
        $this->children = new ArrayCollection();
        $this->allowedGroups = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateTree(ExecutionContextInterface $context): void
    {
        if (null === $this->parent && \in_array($this->slug, self::RESERVED_SLUGS, true)) {
            $context->buildViolation('Cette adresse est utilisée par le site.')->atPath('slug')->addViolation();
        }

        $depth = 1;
        for ($ancestor = $this->parent; null !== $ancestor; $ancestor = $ancestor->getParent()) {
            if ($ancestor === $this) {
                $context->buildViolation('Une page ne peut pas être rangée sous elle-même.')->atPath('parent')->addViolation();

                return;
            }
            ++$depth;
        }
        if ($depth > self::MAX_DEPTH) {
            $context->buildViolation(\sprintf('Au plus %d niveaux de pages.', self::MAX_DEPTH))->atPath('parent')->addViolation();
        }
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

    public function getParent(): ?Page
    {
        return $this->parent;
    }

    public function setParent(?Page $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, Page>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * Pages from the root down to this one.
     *
     * @return list<Page>
     */
    public function getLineage(): array
    {
        $lineage = [];
        for ($page = $this; null !== $page; $page = $page->getParent()) {
            array_unshift($lineage, $page);
        }

        return $lineage;
    }

    /** Page whose highlight is shown in the header: this one, or the nearest ancestor that has one. */
    public function getHighlightSource(): ?Page
    {
        for ($page = $this; null !== $page; $page = $page->getParent()) {
            if (null !== $page->getHighlight() && '' !== $page->getHighlight()) {
                return $page;
            }
        }

        return null;
    }

    /** URL path without leading slash, e.g. "le-club/textes-officiels/statuts". */
    public function getPath(): string
    {
        return implode('/', array_map(static fn (Page $page): string => $page->getSlug(), $this->getLineage()));
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

    public function getKicker(): ?string
    {
        return $this->kicker;
    }

    public function setKicker(?string $kicker): static
    {
        $this->kicker = $kicker;

        return $this;
    }

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
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

    public function getLinkLabel(): ?string
    {
        return $this->linkLabel;
    }

    public function setLinkLabel(?string $linkLabel): static
    {
        $this->linkLabel = $linkLabel;

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

    public function getWordpressId(): ?int
    {
        return $this->wordpressId;
    }

    public function setWordpressId(?int $wordpressId): static
    {
        $this->wordpressId = $wordpressId;

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
        return $this->title;
    }
}
