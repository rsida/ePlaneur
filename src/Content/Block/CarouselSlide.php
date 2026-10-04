<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class CarouselSlide
{
    public function __construct(
        public int $mediaId,
        /** Short label under the thumbnail */
        public string $label,
        public ?string $caption = null,
    ) {
    }
}
