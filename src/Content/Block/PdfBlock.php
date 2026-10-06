<?php

declare(strict_types=1);

namespace App\Content\Block;

/** PDF read inside the page, with its name, size and a download link. */
final readonly class PdfBlock implements BlockInterface
{
    public function __construct(
        #[Field('Fichier PDF', widget: 'media', accept: 'pdf')]
        public int $mediaId,
        #[Field('Titre')]
        public ?string $title = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Pdf;
    }

    public function mediaIds(): array
    {
        return [$this->mediaId];
    }
}
