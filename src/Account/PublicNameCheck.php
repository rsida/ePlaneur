<?php

declare(strict_types=1);

namespace App\Account;

use App\Entity\User;
use App\Repository\SchoolClassRepository;
use App\Repository\UserRepository;

/**
 * Checks the name a member chooses to show to the others (doc/accounts.md#name-shown): a pseudonym
 * when chosen, nobody else's, not their name; and not their name while they are in a class
 * (doc/teacher-space.md), whose pupils may be minors.
 */
final readonly class PublicNameCheck
{
    public function __construct(
        private UserRepository $users,
        private SchoolClassRepository $classes,
    ) {
    }

    /**
     * The error of each field that is wrong, by field name (`nameShown`, `pseudonym`).
     *
     * @return array<string, string>
     */
    public function errors(User $user, string $displayName, NameShown $nameShown, string $pseudonym): array
    {
        if (NameShown::Name === $nameShown && [] !== $this->classes->findOfPupil($user)) {
            return ['nameShown' => 'Tant que vous êtes dans une classe, les autres pilotes voient votre prénom ou un pseudonyme.'];
        }
        if (NameShown::Pseudonym === $nameShown && '' === $pseudonym) {
            return ['pseudonym' => 'Choisissez votre pseudonyme.'];
        }
        $other = '' !== $pseudonym ? $this->users->findOneBy(['pseudonym' => $pseudonym]) : null;
        if (null !== $other && $other !== $user) {
            return ['pseudonym' => 'Ce pseudonyme est déjà pris.'];
        }
        if (NameShown::Pseudonym === $nameShown && 0 === strcasecmp($pseudonym, $displayName)) {
            return ['pseudonym' => 'Choisissez un pseudonyme différent de votre nom.'];
        }

        return [];
    }
}
