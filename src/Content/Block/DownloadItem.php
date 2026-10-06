<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class DownloadItem
{
    public function __construct(
        #[Field('Fichier', widget: 'media', accept: 'file')]
        public int $mediaId,
        #[Field('Titre')]
        public string $title,
        #[Field('Description')]
        public ?string $description = null,
    ) {
    }
}
