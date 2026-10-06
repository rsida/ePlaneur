<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class CarouselSlide
{
    public function __construct(
        #[Field('Image', widget: 'media', accept: 'image')]
        public int $mediaId,
        /** Short label under the thumbnail */
        #[Field('Libellé sous la vignette')]
        public string $label,
        #[Field('Légende')]
        public ?string $caption = null,
    ) {
    }
}
