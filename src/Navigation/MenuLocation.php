<?php

declare(strict_types=1);

namespace App\Navigation;

/**
 * Places where a navigation menu is displayed.
 */
enum MenuLocation: string
{
    /** Header: level 1 = sections, deeper levels = drop-down panels. */
    case Main = 'main';

    /** Footer: level 1 = column titles, level 2 = links. */
    case Footer = 'footer';

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Menu principal',
            self::Footer => 'Pied de page',
        };
    }
}
