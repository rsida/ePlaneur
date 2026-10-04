<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * Closing band: what to remember, with checked points and a call to action. Numbered like a section
 * and listed in the table of contents.
 */
final readonly class TakeawaysBlock implements BlockInterface
{
    /**
     * @param list<string> $points
     */
    public function __construct(
        public string $title,
        public ?string $eyebrow = null,
        public ?string $text = null,
        public array $points = [],
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?string $navTitle = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Takeaways;
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
