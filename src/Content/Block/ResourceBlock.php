<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Highlighted resource card (sidebar), e.g. "Votre mémo avant le vol". */
final readonly class ResourceBlock implements BlockInterface
{
    public function __construct(
        public string $title,
        public ?string $text = null,
        public ?string $linkLabel = null,
        public ?string $linkUrl = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Resource;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
