<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Card of labelled lines ("J'ai compris", "Je me demande"...). */
final readonly class NotesBlock implements BlockInterface
{
    /**
     * @param list<Fact> $rows
     */
    public function __construct(
        #[Field('Titre')]
        public string $title,
        #[Field('Lignes', widget: 'items', item: Fact::class)]
        public array $rows,
        #[Field('Note')]
        public ?string $note = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Notes;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
