<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\RestrictedContentInterface;
use App\Security\Visibility;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Decides who sees a restricted content: `is_granted('CONTENT_VIEW', post)`, or in Twig
 * `{% if is_granted('CONTENT_VIEW', link) %}` to hide a menu link.
 *
 * Users whose group grants every permission (administrators) see everything.
 *
 * @extends Voter<string, RestrictedContentInterface>
 */
final class ContentVoter extends Voter
{
    public const string VIEW = 'CONTENT_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof RestrictedContentInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (Visibility::Public === $subject->getVisibility()) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('Content restricted to logged-in users.');

            return false;
        }

        if (Visibility::Authenticated === $subject->getVisibility() || $user->hasAllPermissions()) {
            return true;
        }

        foreach ($subject->getAllowedGroups() as $group) {
            if ($user->getGroups()->contains($group)) {
                return true;
            }
        }
        $vote?->addReason('Content restricted to groups the user does not belong to.');

        return false;
    }
}
