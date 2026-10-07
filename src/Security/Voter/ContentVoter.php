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
 * Who may see a restricted content (post, page, document, file, menu link):
 *
 * - `CONTENT_VIEW`: open it. Public content, any logged-in user for "Comptes connectés", members of
 *   an allowed group otherwise, a group including it counting as that group
 *   (Group::getIncludedGroups()). Administrators (all permissions) see everything.
 * - `CONTENT_LIST`: see it in lists and menus. Content they may open, and content announced to the
 *   others (shown with a padlock); private content is listed for its audience only.
 *
 * Readers who may not open a content get "Contenu réservé" when it is announced, "not found" when
 * it is private (RestrictedContentResponder).
 *
 * @extends Voter<string, RestrictedContentInterface>
 */
final class ContentVoter extends Voter
{
    public const string VIEW = 'CONTENT_VIEW';

    public const string LIST = 'CONTENT_LIST';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::LIST], true) && $subject instanceof RestrictedContentInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->mayOpen($subject, $token->getUser(), $vote)) {
            return true;
        }

        return self::LIST === $attribute && $subject->isAnnounced();
    }

    private function mayOpen(RestrictedContentInterface $content, mixed $user, ?Vote $vote): bool
    {
        if (Visibility::Public === $content->getVisibility()) {
            return true;
        }

        if (!$user instanceof User) {
            $vote?->addReason('Content restricted to logged-in users.');

            return false;
        }

        if (Visibility::Authenticated === $content->getVisibility() || $user->hasAllPermissions()) {
            return true;
        }

        $groups = $user->getEffectiveGroups();
        foreach ($content->getAllowedGroups() as $group) {
            if (\in_array($group, $groups, true)) {
                return true;
            }
        }
        $vote?->addReason('Content restricted to groups the user does not belong to.');

        return false;
    }
}
