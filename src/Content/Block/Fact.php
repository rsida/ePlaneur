<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Label + short text pair (tab facts, notes, glossary...). */
final readonly class Fact
{
    public function __construct(
        public string $label,
        public string $text,
    ) {
    }
}
