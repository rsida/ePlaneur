<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Highlighted box: info (À retenir), tip (Conseil) or warning (Vigilance). */
final readonly class CalloutBlock implements BlockInterface
{
    public const array VARIANTS = ['info', 'tip', 'warning'];

    public function __construct(
        #[Field('Titre', inline: true)]
        public string $title,
        #[Field('Texte', widget: 'rich', inline: true)]
        public string $html,
        #[Field('Variante', widget: 'choice', choices: ['info' => 'À retenir', 'tip' => 'Conseil', 'warning' => 'Vigilance'])]
        public string $variant = 'info',
        #[Field('Afficher l’icône')]
        public bool $showIcon = true,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Callout;
    }

    public function mediaIds(): array
    {
        return [];
    }

    /** Variant to render, falling back to info for unknown values. */
    public function safeVariant(): string
    {
        return \in_array($this->variant, self::VARIANTS, true) ? $this->variant : 'info';
    }
}
