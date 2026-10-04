<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Two (or more) pictures side by side, each with its caption. */
final readonly class ImageGridBlock implements BlockInterface
{
    /**
     * @param list<CaptionedImage> $images
     */
    public function __construct(
        public array $images,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::ImageGrid;
    }

    public function mediaIds(): array
    {
        return array_map(static fn (CaptionedImage $image): int => $image->mediaId, $this->images);
    }
}
