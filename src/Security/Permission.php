<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Catalogue of the actions that can be granted to a group.
 *
 * The list is fixed in code because code checks it (`is_granted('USER_MANAGE')`); which group holds
 * which permission is data, edited by an administrator. Add a case here when a feature needs a new
 * right, then grant it to groups.
 */
enum Permission: string
{
    case AdminAccess = 'ADMIN_ACCESS';
    case UserManage = 'USER_MANAGE';
    case GroupManage = 'GROUP_MANAGE';

    public function label(): string
    {
        return match ($this) {
            self::AdminAccess => 'Accéder à l’administration',
            self::UserManage => 'Gérer les comptes',
            self::GroupManage => 'Gérer les groupes et leurs droits',
        };
    }
}
