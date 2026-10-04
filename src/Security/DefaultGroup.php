<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Groups every installation has (created by a migration, and by the dev fixtures).
 *
 * Administrators can create other groups (Rédacteur, Bienfaiteur...) and change the permissions of
 * these ones; only their existence and code are fixed.
 */
enum DefaultGroup: string
{
    case Member = 'member';
    case Committee = 'committee';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Membre',
            self::Committee => 'Comité',
            self::Admin => 'Administrateur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Member => 'Adhérent à jour de sa cotisation, validé par le club.',
            self::Committee => 'Membre du Comité Directeur.',
            self::Admin => 'Administrateur du site : tous les droits.',
        };
    }

    public function hasAllPermissions(): bool
    {
        return self::Admin === $this;
    }
}
