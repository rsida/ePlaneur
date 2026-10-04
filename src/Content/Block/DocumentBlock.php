<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * One official document highlighted in a card: title, format, version, pages, size, dated mentions.
 */
final readonly class DocumentBlock implements BlockInterface
{
    public function __construct(
        public int $documentId,
        public ?string $linkLabel = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Document;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
