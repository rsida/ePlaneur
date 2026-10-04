<?php

declare(strict_types=1);

namespace App\Content;

use App\Entity\Page;

/**
 * Everything a page renders, prepared by {@see PagePresenter}.
 */
final readonly class PageView
{
    /**
     * @param list<PlacedBlock> $body
     * @param list<PlacedBlock> $aside
     * @param list<TocEntry>    $toc
     * @param list<Page>        $children child pages the reader may see (cards)
     * @param list<Page>        $siblings pages of the same level the reader may see (side navigation)
     */
    public function __construct(
        public Page $page,
        public array $body,
        public array $aside,
        public array $toc,
        public array $children,
        public array $siblings,
    ) {
    }
}
