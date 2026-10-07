<?php

declare(strict_types=1);

namespace App\Entity;

use App\Content\LinkUrl;
use App\Navigation\MenuLocation;
use App\Repository\MenuItemRepository;
use App\Security\RestrictedContentInterface;
use App\Security\Visibility;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Navigation link. Items form one tree per menu location; an item links to a page, to a URL
 * (internal path like "/actualites" or external site), or to nothing (a heading grouping its
 * children). It is shown only to readers allowed by its own visibility and by its target page's.
 */
#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\Index(name: 'menu_item_location_idx', columns: ['location'])]
class MenuItem implements RestrictedContentInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?MenuItem $parent = null;

    /** @var Collection<int, MenuItem> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Page $page = null;

    /** Internal path ("/actualites", "/#vols") or external URL ("https://…"). */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(pattern: LinkUrl::PATTERN, message: LinkUrl::MESSAGE)]
    private ?string $url = null;

    /** Short note under the link ("Document PDF", "Lien externe"); for a section, the drop-down kicker. */
    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $description = null;

    #[ORM\Column(length: 20, enumType: Visibility::class, options: ['default' => 'public'])]
    private Visibility $visibility = Visibility::Public;

    /** @var Collection<int, Group> */
    #[ORM\ManyToMany(targetEntity: Group::class)]
    #[ORM\JoinTable(name: 'menu_item_allowed_group')]
    private Collection $allowedGroups;

    public function __construct(
        #[ORM\Column(length: 20, enumType: MenuLocation::class)]
        private MenuLocation $location,
        #[ORM\Column(length: 100)]
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        private string $label,
    ) {
        $this->children = new ArrayCollection();
        $this->allowedGroups = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $context): void
    {
        if (null !== $this->page && null !== $this->url) {
            $context->buildViolation('Choisissez une page ou une adresse, pas les deux.')->atPath('url')->addViolation();
        }
        if (null !== $this->parent && $this->parent->getLocation() !== $this->location) {
            $context->buildViolation('Le lien parent appartient à un autre menu.')->atPath('parent')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): MenuLocation
    {
        return $this->location;
    }

    public function setLocation(MenuLocation $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getParent(): ?MenuItem
    {
        return $this->parent;
    }

    public function setParent(?MenuItem $parent): static
    {
        $this->parent = $parent;
        $parent?->getChildren()->add($this);

        return $this;
    }

    /**
     * @return Collection<int, MenuItem>
     */
    public function getChildren(): Collection
    {
        return $this->children;
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

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

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

    public function isExternal(): bool
    {
        return null !== $this->url && 1 === preg_match('~^https?://~', $this->url);
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

    /** Label with its parents ("Le Club › Textes officiels"), to choose a parent in the back-office. */
    /** A link outside the reader's audience is never shown: it has nothing to announce. */
    public function isAnnounced(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return null !== $this->parent ? $this->parent.' › '.$this->label : $this->label;
    }
}
