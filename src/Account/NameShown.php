<?php

declare(strict_types=1);

namespace App\Account;

/**
 * How a member is named to the others in rankings, records, flights and sessions (doc/accounts.md#name-shown):
 * by their name, their first name only or a pseudonym. Meant for the minors; the rule (who must
 * choose, with the consent of a parent) is for the committee to decide.
 */
enum NameShown: string
{
    case Name = 'name';
    case FirstName = 'first_name';
    case Pseudonym = 'pseudonym';

    public function label(): string
    {
        return match ($this) {
            self::Name => 'Mon nom affiché',
            self::FirstName => 'Mon prénom seulement',
            self::Pseudonym => 'Un pseudonyme',
        };
    }
}
