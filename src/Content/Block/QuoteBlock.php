<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class QuoteBlock implements BlockInterface
{
    public function __construct(
        public string $text,
        public ?string $attribution = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Quote;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
