<?php

declare(strict_types=1);

namespace App\Content\Block;

/** One or more paragraphs of rich text. */
final readonly class TextBlock implements BlockInterface
{
    public function __construct(
        public string $html,
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
