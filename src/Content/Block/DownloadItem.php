<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class DownloadItem
{
    public function __construct(
        public int $mediaId,
        public string $title,
        public ?string $description = null,
    ) {
    }
}
