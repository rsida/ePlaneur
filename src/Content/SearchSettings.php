<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\Media;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Optional search settings of a post or a page ("Référencement" in the editor). Each one replaces
 * a value taken from the content: the title, the lead, the cover. See doc/seo.md.
 */
trait SearchSettings
{
    /** Title for search engines and link previews, instead of the title. */
    #[ORM\Column(length: 70, nullable: true)]
    #[Assert\Length(max: 70)]
    private ?string $seoTitle = null;

    /** Description for search engines and link previews, instead of the lead. */
    #[ORM\Column(length: 300, nullable: true)]
    #[Assert\Length(max: 300)]
    private ?string $seoDescription = null;

    /** Picture of link previews, instead of the cover (or the picture of the site). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $seoImage = null;

    /** Kept out of search engines and of sitemap.xml even when public. */
    #[ORM\Column(options: ['default' => false])]
    private bool $noIndex = false;

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(?string $seoTitle): static
    {
        $this->seoTitle = '' !== trim($seoTitle ?? '') ? trim((string) $seoTitle) : null;

        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $seoDescription): static
    {
        $this->seoDescription = '' !== trim($seoDescription ?? '') ? trim((string) $seoDescription) : null;

        return $this;
    }

    public function getSeoImage(): ?Media
    {
        return $this->seoImage;
    }

    public function setSeoImage(?Media $seoImage): static
    {
        $this->seoImage = $seoImage;

        return $this;
    }

    public function isNoIndex(): bool
    {
        return $this->noIndex;
    }

    public function setNoIndex(bool $noIndex): static
    {
        $this->noIndex = $noIndex;

        return $this;
    }
}
