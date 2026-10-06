<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Gallery: one large picture, previous/next arrows and labelled thumbnails. */
final readonly class CarouselBlock implements BlockInterface
{
    /**
     * @param list<CarouselSlide> $slides
     */
    public function __construct(
        #[Field('Diapositives', widget: 'items', item: CarouselSlide::class)]
        public array $slides,
        #[Field('Titre')]
        public ?string $title = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Carousel;
    }

    public function mediaIds(): array
    {
        return array_map(static fn (CarouselSlide $slide): int => $slide->mediaId, $this->slides);
    }
}
