<?php

declare(strict_types=1);

namespace App\Content\Block;

final readonly class Tab
{
    /**
     * @param list<Fact> $facts
     */
    public function __construct(
        #[Field('Libellé de l’onglet')]
        public string $label,
        #[Field('Titre')]
        public string $title,
        #[Field('Texte', widget: 'rich')]
        public ?string $html = null,
        #[Field('Repères', widget: 'items', item: Fact::class)]
        public array $facts = [],
        #[Field('Texte du lien')]
        public ?string $linkLabel = null,
        #[Field('Adresse du lien', widget: 'url')]
        public ?string $linkUrl = null,
    ) {
    }
}
