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
        #[Field('Titre')]
        public string $title,
        #[Field('Points à cocher', widget: 'lines')]
        public array $items,
        #[Field('Note')]
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
