<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Accordion of questions and answers; the first one can start open. */
final readonly class FaqBlock implements BlockInterface
{
    /**
     * @param list<FaqItem> $items
     */
    public function __construct(
        public array $items,
        public ?string $title = null,
        public bool $openFirst = true,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Faq;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
