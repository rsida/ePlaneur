<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * A content block: an immutable value object rebuilt from the JSON stored on the content (see
 * {@see BlockFactory}) and rendered by the Twig component named by its {@see BlockType}.
 *
 * Rich text properties hold limited HTML, always printed with `|sanitize_html('app.rich_text')`.
 */
interface BlockInterface
{
    public static function type(): BlockType;

    /**
     * Ids of the media (images, files) the block displays, preloaded before rendering.
     *
     * @return list<int>
     */
    public function mediaIds(): array;
}
