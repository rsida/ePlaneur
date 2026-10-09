<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\NetworkSession;
use App\Entity\User;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * SESSION_EDIT (a network session): its organiser while they may organise sessions
 * (SESSION_ORGANISE), or whoever manages every session (SESSION_MANAGE). Editing includes adding the
 * results and deleting the session.
 *
 * @extends Voter<string, NetworkSession>
 */
final class SessionVoter extends Voter
{
    public const string EDIT = 'SESSION_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof NetworkSession;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $user->hasPermission(Permission::SessionManage)
            || ($subject->getOrganiser() === $user && $user->hasPermission(Permission::SessionOrganise));
    }
}
