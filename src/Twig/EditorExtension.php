<?php

declare(strict_types=1);

namespace App\Twig;

use App\Content\Block\BlockType;
use Twig\Attribute\AsTwigFunction;

/**
 * Helpers of the back-office block editor templates.
 */
final class EditorExtension
{
    /**
     * @return list<BlockType>
     */
    #[AsTwigFunction('block_types')]
    public function blockTypes(): array
    {
        return BlockType::cases();
    }
}
