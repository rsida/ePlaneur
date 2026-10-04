<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class FaqItem
{
    public function __construct(
        public string $question,
        /** Rich text */
        public string $answer,
    ) {
    }
}
