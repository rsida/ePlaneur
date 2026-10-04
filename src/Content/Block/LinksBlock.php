<?php

declare(strict_types=1);

namespace App\Content\Block;

/** List of links to go further (internal or external): rows with description, or compact arrow links. */
final readonly class LinksBlock implements BlockInterface
{
    /**
     * @param list<LinkItem> $links
     */
    public function __construct(
        public array $links,
        /** "rows" (title, description, arrow) or "arrows" (compact arrow links) */
        public string $style = 'rows',
        public ?string $note = null,
    ) {
    }

    public function safeStyle(): string
    {
        return 'arrows' === $this->style ? 'arrows' : 'rows';
    }

    public static function type(): BlockType
    {
        return BlockType::Links;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
