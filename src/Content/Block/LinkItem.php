<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class LinkItem
{
    public function __construct(
        #[Field('Titre')]
        public string $title,
        #[Field('Adresse', widget: 'url')]
        public string $url,
        #[Field('Description')]
        public ?string $description = null,
    ) {
    }
}
