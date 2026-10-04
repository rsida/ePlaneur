<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * Official documents of a category (all categories when categoryId is null), grouped by category.
 * Only the documents the reader may see are listed.
 */
final readonly class DocumentsBlock implements BlockInterface
{
    public const array LAYOUTS = ['list', 'cards'];

    public function __construct(
        public ?int $categoryId = null,
        public ?string $title = null,
        /** Default layout; readers can switch between list and cards */
        public string $layout = 'list',
        public ?string $intro = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Documents;
    }

    public function mediaIds(): array
    {
        return [];
    }

    public function safeLayout(): string
    {
        return \in_array($this->layout, self::LAYOUTS, true) ? $this->layout : 'list';
    }
}
