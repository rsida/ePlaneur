<?php

declare(strict_types=1);

namespace App\Content\Block;

/** List of links to go further (internal or external): rows with description, or compact arrow links. */
final readonly class LinksBlock implements BlockInterface
{
    /**
     * @param list<LinkItem> $links
     */
    public function __construct(
        #[Field('Liens', widget: 'items', item: LinkItem::class)]
        public array $links,
        /** "rows" (title, description, arrow) or "arrows" (compact arrow links) */
        #[Field('Présentation', widget: 'choice', choices: ['rows' => 'Lignes avec description', 'arrows' => 'Liens fléchés compacts'])]
        public string $style = 'rows',
        #[Field('Note')]
        public ?string $note = null,
    ) {
    }

    public function safeStyle(): string
    {
        return 'arrows' === $this->style ? 'arrows' : 'rows';
    }

    public static function type(): BlockType
    {
        return BlockType::Links;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
