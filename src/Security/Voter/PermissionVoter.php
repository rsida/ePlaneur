<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants a {@see Permission} (`is_granted('USER_MANAGE')`, `#[IsGranted('USER_MANAGE')]`) when one of
 * the user's groups holds it.
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return null !== Permission::tryFrom($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('Not logged in.');

            return false;
        }

        $granted = $user->hasPermission(Permission::from($attribute));
        if (!$granted) {
            $vote?->addReason(\sprintf('None of the user\'s groups grants %s.', $attribute));
        }

        return $granted;
    }
}
