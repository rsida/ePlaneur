<?php

declare(strict_types=1);

namespace App\Content;

/** Table of contents entry: "01" + title, linking to the anchor of its section. */
final readonly class TocEntry
{
    public function __construct(
        public string $number,
        public string $title,
        public string $anchor,
    ) {
    }
}
