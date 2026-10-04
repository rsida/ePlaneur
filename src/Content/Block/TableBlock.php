<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Table with a header row; the first column is shown as row headers. */
final readonly class TableBlock implements BlockInterface
{
    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
        public ?string $caption = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Table;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
