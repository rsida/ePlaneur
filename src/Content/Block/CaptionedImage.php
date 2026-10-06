<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Picture with its caption, part of image blocks. */
final readonly class CaptionedImage
{
    public function __construct(
        #[Field('Image', widget: 'media', accept: 'image')]
        public int $mediaId,
        #[Field('Légende')]
        public ?string $caption = null,
    ) {
    }
}
