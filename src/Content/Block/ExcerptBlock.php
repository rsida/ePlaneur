<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * Quoted passage of a reference text with its title and reference ("Statuts V23 · Article 9").
 */
final readonly class ExcerptBlock implements BlockInterface
{
    public function __construct(
        #[Field('Titre', inline: true)]
        public string $title,
        #[Field('Extrait', widget: 'textarea', inline: true)]
        public string $text,
        #[Field('Référence')]
        public ?string $reference = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Excerpt;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
