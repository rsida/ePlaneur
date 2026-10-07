<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Audience of a reserved content, for its access tag: "Membre · Comité", "Connectés", or null for
 * public content. Used by the menus and the `access_label()` Twig function.
 */
final class AccessLabel
{
    public static function of(RestrictedContentInterface $content): ?string
    {
        return match ($content->getVisibility()) {
            Visibility::Public => null,
            Visibility::Authenticated => 'Connectés',
            Visibility::Groups => implode(' · ', array_map(static fn ($group): string => $group->getName(), [...$content->getAllowedGroups()])) ?: 'Réservé',
        };
    }
}
