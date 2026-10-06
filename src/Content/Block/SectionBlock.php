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
        #[Field('Titre', inline: true)]
        public string $title,
        #[Field('Surtitre', help: 'Après le numéro : « 01 / Premières ailes ».')]
        public ?string $eyebrow = null,
        #[Field('Introduction', widget: 'textarea')]
        public ?string $intro = null,
        #[Field('Titre dans le sommaire', help: 'Plus court que le titre ; vide = le titre.')]
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
