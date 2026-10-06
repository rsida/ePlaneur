<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * Describes a stored property of a block (or of a block item) for the editor: label, widget and,
 * for lists, the class of their items. Put it on the promoted constructor parameters.
 *
 * Widgets: text, textarea, rich (limited HTML), bool, choice, url, media (int id of a Media),
 * document, document_category, lines (list of strings), items (list of `item` objects), rows (table).
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final readonly class Field
{
    /**
     * @param array<string, string> $choices value => label, for the "choice" widget
     * @param class-string|null     $item    item class of an "items" list
     * @param bool                  $inline  edited in place in the canvas, hidden from the settings panel
     * @param string|null           $accept  media kind for the "media" widget: image, pdf or file
     */
    public function __construct(
        public string $label,
        public ?string $widget = null,
        public ?string $help = null,
        public array $choices = [],
        public ?string $item = null,
        public bool $inline = false,
        public ?string $accept = null,
    ) {
    }
}
