<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Subtitle inside a section. */
final readonly class HeadingBlock implements BlockInterface
{
    public function __construct(
        public string $text,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Heading;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
