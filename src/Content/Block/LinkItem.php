<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class LinkItem
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $description = null,
    ) {
    }
}
