<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class StepItem
{
    public function __construct(
        public string $title,
        public ?string $text = null,
    ) {
    }
}
