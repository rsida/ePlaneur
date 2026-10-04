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
        public string $title,
        public array $rows,
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
