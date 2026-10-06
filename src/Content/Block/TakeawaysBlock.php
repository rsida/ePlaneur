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
        #[Field('Titre', inline: true)]
        public string $title,
        #[Field('Surtitre')]
        public ?string $eyebrow = null,
        #[Field('Texte', widget: 'textarea')]
        public ?string $text = null,
        #[Field('Points clés', widget: 'lines')]
        public array $points = [],
        #[Field('Texte du bouton')]
        public ?string $ctaLabel = null,
        #[Field('Adresse du bouton', widget: 'url')]
        public ?string $ctaUrl = null,
        #[Field('Titre dans le sommaire')]
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
