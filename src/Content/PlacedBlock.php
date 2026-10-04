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
    /**
     * @param array<string, mixed> $context data the block needs from its page (e.g. the child pages)
     */
    public function __construct(
        public BlockInterface $block,
        public string $key,
        public ?TocEntry $toc = null,
        public array $context = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function withContext(array $context): self
    {
        return new self($this->block, $this->key, $this->toc, [...$this->context, ...$context]);
    }

    public function component(): string
    {
        return $this->block::type()->component();
    }
}
