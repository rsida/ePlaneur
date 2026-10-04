<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Picture over the reading width, with caption (the credit comes from the media). */
final readonly class ImageBlock implements BlockInterface
{
    public function __construct(
        public int $mediaId,
        public ?string $caption = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Image;
    }

    public function mediaIds(): array
    {
        return [$this->mediaId];
    }
}
