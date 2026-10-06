<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Subtitle inside a section. */
final readonly class HeadingBlock implements BlockInterface
{
    public function __construct(
        #[Field('Sous-titre', inline: true)]
        public string $text,
        /** "md" (default, 27px) or "lg" (34px, a heading that opens a group of blocks) */
        #[Field('Taille', widget: 'choice', choices: ['md' => 'Normale', 'lg' => 'Grande (ouvre un groupe de blocs)'])]
        public string $size = 'md',
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Heading;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
