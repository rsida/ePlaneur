<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Bulleted or numbered list; items are rich text (bold lead words...). */
final readonly class ListBlock implements BlockInterface
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        public array $items,
        public bool $ordered = false,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::List;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
