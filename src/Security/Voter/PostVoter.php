<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Post;
use App\Entity\User;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * `is_granted('POST_WRITE', post)`: POST_EDIT edits every post; POST_CREATE writes new posts and
 * edits the ones the user is the author of.
 *
 * @extends Voter<string, Post>
 */
final class PostVoter extends Voter
{
    public const string WRITE = 'POST_WRITE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::WRITE === $attribute && $subject instanceof Post;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->hasPermission(Permission::PostEdit)) {
            return true;
        }

        $own = null === $subject->getId() || $subject->getAuthor() === $user;
        if (!$own) {
            $vote?->addReason('Only the author or POST_EDIT may edit this post.');
        }

        return $own && $user->hasPermission(Permission::PostCreate);
    }
}
