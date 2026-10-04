<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Block\BlockInterface;

/**
 * A block in its page: a stable key (DOM ids, saved checklist state) and, for numbered blocks
 * (sections, takeaways), their table of contents entry.
 */
final readonly class PlacedBlock
{
    public function __construct(
        public BlockInterface $block,
        public string $key,
        public ?TocEntry $toc = null,
    ) {
    }

    public function component(): string
    {
        return $this->block::type()->component();
    }
}
