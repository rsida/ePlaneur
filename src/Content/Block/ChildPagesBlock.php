<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * Cards of the sub-pages of the current page the reader may see (title, excerpt, access tag, link).
 */
final readonly class ChildPagesBlock implements BlockInterface
{
    public function __construct(
        #[Field('Surtitre')]
        public ?string $eyebrow = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::ChildPages;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
