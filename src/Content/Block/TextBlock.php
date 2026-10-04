<?php

declare(strict_types=1);

namespace App\Content\Block;

/** One or more paragraphs of rich text. */
final readonly class TextBlock implements BlockInterface
{
    public function __construct(
        public string $html,
        /** Secondary text in a muted colour */
        public bool $muted = false,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Text;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
