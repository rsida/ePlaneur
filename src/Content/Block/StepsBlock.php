<?php

declare(strict_types=1);

namespace App\Content\Block;

/** Numbered steps (01, 02...) with a title and a short text each. */
final readonly class StepsBlock implements BlockInterface
{
    /**
     * @param list<StepItem> $steps
     */
    public function __construct(
        public array $steps,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Steps;
    }

    public function mediaIds(): array
    {
        return [];
    }
}
