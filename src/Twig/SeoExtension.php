<?php

declare(strict_types=1);

namespace App\Twig;

use App\Content\PlacedBlock;
use App\Entity\Post;
use App\Seo\SearchEngines;
use App\Seo\StructuredData;
use Twig\Attribute\AsTwigFunction;

/**
 * `seo_indexable()` and `seo_canonical()`: the robots directive and the canonical address of the
 * current page, written in the head of base.html.twig. `seo_site()`, `seo_article()`,
 * `seo_breadcrumb()`, `seo_blocks()` and `json_ld(graph)`: schema.org descriptions (StructuredData).
 */
final readonly class SeoExtension
{
    public function __construct(
        private SearchEngines $searchEngines,
        private StructuredData $structuredData,
    ) {
    }

    #[AsTwigFunction('seo_indexable')]
    public function indexable(): bool
    {
        return $this->searchEngines->isIndexable();
    }

    #[AsTwigFunction('seo_canonical')]
    public function canonical(): ?string
    {
        return $this->searchEngines->canonical();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[AsTwigFunction('seo_site')]
    public function site(): array
    {
        return $this->structuredData->site();
    }

    /**
     * @return array<string, mixed>
     */
    #[AsTwigFunction('seo_article')]
    public function article(Post $post, string $title, string $description): array
    {
        return $this->structuredData->article($post, $title, $description);
    }

    /**
     * @param list<array{label: string, href: string}> $crumbs
     *
     * @return array<string, mixed>
     */
    #[AsTwigFunction('seo_breadcrumb')]
    public function breadcrumb(array $crumbs): array
    {
        return $this->structuredData->breadcrumb($crumbs);
    }

    /**
     * @param list<PlacedBlock> ...$zones
     *
     * @return list<array<string, mixed>>
     */
    #[AsTwigFunction('seo_blocks')]
    public function blocks(string $url, array ...$zones): array
    {
        return $this->structuredData->blocks($url, ...$zones);
    }

    /**
     * @param list<array<string, mixed>> $graph
     */
    #[AsTwigFunction('json_ld', isSafe: ['html'])]
    public function jsonLd(array $graph): string
    {
        return $this->structuredData->script($graph);
    }
}
