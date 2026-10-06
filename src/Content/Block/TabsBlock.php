<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Tabbed panels (one per simulator version, profile...). */
final readonly class TabsBlock implements BlockInterface
{
    /**
     * @param list<Tab> $tabs
     */
    public function __construct(
        #[Field('Onglets', widget: 'items', item: Tab::class)]
        public array $tabs,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Tabs;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
