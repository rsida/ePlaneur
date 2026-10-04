<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class Tab
{
    /**
     * @param list<Fact> $facts
     */
    public function __construct(
        public string $label,
        public string $title,
        public ?string $html = null,
        public array $facts = [],
        public ?string $linkLabel = null,
        public ?string $linkUrl = null,
    ) {
    }
}
