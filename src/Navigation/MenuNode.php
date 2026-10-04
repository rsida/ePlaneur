<?php

declare(strict_types=1);

namespace App\Navigation;

/**
 * A menu link ready to render: only the links the reader may see are built.
 */
final readonly class MenuNode
{
    /**
     * @param list<MenuNode> $children
     */
    public function __construct(
        public string $label,
        /** Null for a heading that only groups its children */
        public ?string $href,
        public bool $external,
        /** The current page is this link or one of its descendants */
        public bool $active,
        /** Its target is reserved: shown with an access tag ($access) that opens on "Contenu réservé" if not allowed */
        public bool $restricted,
        public array $children = [],
        /** Short note under the link ("Document PDF"), or the drop-down kicker of a section */
        public ?string $description = null,
        /** Access tag label of a restricted link: group names or "Connectés" */
        public ?string $access = null,
        /** The current page is exactly this link */
        public bool $current = false,
    ) {
    }

    public function hasChildren(): bool
    {
        return [] !== $this->children;
    }

    /**
     * Labels from this link down to the current page ("Textes officiels › Statuts"), when active.
     *
     * @return list<string>
     */
    public function activeTrail(): array
    {
        foreach ($this->children as $child) {
            if ($child->active) {
                return [$child->label, ...$child->activeTrail()];
            }
        }

        return [];
    }
}
