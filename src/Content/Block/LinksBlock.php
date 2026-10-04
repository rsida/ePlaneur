<?php

declare(strict_types=1);

namespace App\Content\Block;

/** List of links to go further (internal or external). */
final readonly class LinksBlock implements BlockInterface
{
    /**
     * @param list<LinkItem> $links
     */
    public function __construct(
        public array $links,
    ) {
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
