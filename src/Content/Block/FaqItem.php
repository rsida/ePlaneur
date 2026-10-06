<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class FaqItem
{
    public function __construct(
        #[Field('Question')]
        public string $question,
        /** Rich text */
        #[Field('Réponse', widget: 'rich')]
        public string $answer,
    ) {
    }
}
