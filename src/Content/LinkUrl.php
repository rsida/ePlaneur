<?php

declare(strict_types=1);

namespace App\Content;

/**
 * Addresses editors may give to links (blocks, menus): a path of the site ("/actualites", "/#vols"),
 * an anchor ("#reglement"), a web address or an e-mail. Anything else, such as "javascript:", could
 * run a script in the reader's session.
 */
final class LinkUrl
{
    public const string PATTERN = '~^(/(?!/)|#|https?://|mailto:)~i';

    public const string MESSAGE = 'Une adresse commençant par /, #, http(s):// ou mailto:.';

    public static function isSafe(string $url): bool
    {
        return 1 === preg_match(self::PATTERN, trim($url));
    }
}
