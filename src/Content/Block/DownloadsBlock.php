<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Download cards showing the format and the size of each file. */
final readonly class DownloadsBlock implements BlockInterface
{
    /**
     * @param list<DownloadItem> $files
     */
    public function __construct(
        public array $files,
        public ?string $note = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Downloads;
    }

    public function mediaIds(): array
    {
        return array_map(static fn (DownloadItem $file): int => $file->mediaId, $this->files);
    }
}
