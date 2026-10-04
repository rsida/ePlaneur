<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * Numbered section title of an article: "01 / Premières ailes" + title + intro. Each section is an
 * entry of the table of contents (navTitle, or the title when empty).
 */
final readonly class SectionBlock implements BlockInterface
{
    public function __construct(
        public string $title,
        public ?string $eyebrow = null,
        public ?string $intro = null,
        public ?string $navTitle = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Section;
    }

    public function mediaIds(): array
    {
        return [];
    }

    public function tocTitle(): string
    {
        return $this->navTitle ?: $this->title;
    }
}
