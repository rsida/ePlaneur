<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Who can see a piece of content (menu link, post, page, document...).
 */
enum Visibility: string
{
    /** Everyone, including visitors. */
    case Public = 'public';

    /** Any logged-in user with a verified account. */
    case Authenticated = 'authenticated';

    /** Only members of the groups attached to the content. */
    case Groups = 'groups';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Tout le monde',
            self::Authenticated => 'Utilisateurs connectés',
            self::Groups => 'Groupes choisis',
        };
    }
}
