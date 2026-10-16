<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\SchoolClass;
use App\Entity\User;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * CLASS_FOLLOW (a class): see the progression of its pupils and run it (link, pupils, deletion): its
 * teacher while they may teach (CLASS_TEACH), or an administrator.
 *
 * @extends Voter<string, SchoolClass>
 */
final class SchoolClassVoter extends Voter
{
    public const string FOLLOW = 'CLASS_FOLLOW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::FOLLOW === $attribute && $subject instanceof SchoolClass;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $user->hasAllPermissions()
            || ($subject->getTeacher() === $user && $user->hasPermission(Permission::ClassTeach));
    }
}
