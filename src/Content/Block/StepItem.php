<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class StepItem
{
    public function __construct(
        #[Field('Titre')]
        public string $title,
        #[Field('Texte', widget: 'textarea')]
        public ?string $text = null,
    ) {
    }
}
