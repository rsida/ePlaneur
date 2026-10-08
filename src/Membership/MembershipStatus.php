<?php

declare(strict_types=1);

namespace App\Membership;

/**
 * Where a membership request stands: requested by the member, paid, validated by the club (the
 * account then joins the "Membre" group), refused or cancelled.
 */
enum MembershipStatus: string
{
    case Requested = 'requested';
    case Paid = 'paid';
    case Validated = 'validated';
    case Refused = 'refused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Demande envoyée, paiement attendu',
            self::Paid => 'Payée, à valider par le club',
            self::Validated => 'Validée',
            self::Refused => 'Refusée',
            self::Cancelled => 'Annulée',
        };
    }

    /** Still counts for the season: a new request is not possible. */
    public function isActive(): bool
    {
        return \in_array($this, [self::Requested, self::Paid, self::Validated], true);
    }
}
