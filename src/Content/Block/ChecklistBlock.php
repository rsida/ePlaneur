<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Interactive checklist; the reader's ticks are kept in their browser. */
final readonly class ChecklistBlock implements BlockInterface
{
    /**
     * @param list<string> $items
     */
    public function __construct(
        public string $title,
        public array $items,
        public ?string $note = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Checklist;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
