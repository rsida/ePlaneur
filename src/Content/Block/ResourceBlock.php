<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Highlighted resource card (sidebar), e.g. "Votre mémo avant le vol". */
final readonly class ResourceBlock implements BlockInterface
{
    public function __construct(
        #[Field('Titre', inline: true)]
        public string $title,
        #[Field('Texte', widget: 'textarea')]
        public ?string $text = null,
        #[Field('Texte du lien')]
        public ?string $linkLabel = null,
        #[Field('Adresse du lien', widget: 'url')]
        public ?string $linkUrl = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Resource;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
