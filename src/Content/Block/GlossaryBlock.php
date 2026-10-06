<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Terms and their definitions. */
final readonly class GlossaryBlock implements BlockInterface
{
    /**
     * @param list<Fact> $entries label = term, text = definition
     */
    public function __construct(
        #[Field('Termes', widget: 'items', item: Fact::class)]
        public array $entries,
        #[Field('Titre')]
        public ?string $title = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Glossary;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
