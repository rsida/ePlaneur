<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Highlighted box: info (À retenir), tip (Conseil) or warning (Vigilance). */
final readonly class CalloutBlock implements BlockInterface
{
    public const array VARIANTS = ['info', 'tip', 'warning'];

    public function __construct(
        public string $title,
        public string $html,
        public string $variant = 'info',
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
