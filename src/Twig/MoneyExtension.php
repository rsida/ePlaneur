<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Amounts stored in cents, for people: `2500|euros` → "25 €", `1050|euros` → "10,50 €".
 */
final class MoneyExtension
{
    #[AsTwigFilter('euros')]
    public static function euros(int $cents): string
    {
        return number_format($cents / 100, 0 !== $cents % 100 ? 2 : 0, ',', "\u{202F}")."\u{A0}€";
    }
}
