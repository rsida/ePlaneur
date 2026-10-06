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
        #[Field('Questions', widget: 'items', item: FaqItem::class)]
        public array $items,
        #[Field('Titre')]
        public ?string $title = null,
        #[Field('Ouvrir la première question')]
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
