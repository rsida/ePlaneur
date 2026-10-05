<?php

declare(strict_types=1);

namespace App\Navigation;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Places where a navigation menu is displayed.
 */
enum MenuLocation: string implements TranslatableInterface
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

    /** French label, shown as is by forms and the back-office (EasyAdmin renders translatable enums). */
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->label();
    }
}
