<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Flight;
use App\Entity\FlightComment;
use App\Entity\User;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Rights on flights:
 *
 * - FLIGHT_VIEW (a flight): any logged-in user, and visitors for a public flight;
 * - FLIGHT_PUBLISH (a flight): its pilot (makes it public or not);
 * - FLIGHT_DELETE (a flight): the pilot who uploaded it, or an administrator;
 * - FLIGHT_COMMENT (a flight): its pilot, or a trainer (FLIGHT_REVIEW);
 * - FLIGHT_COMMENT_DELETE (a comment): its author, or an administrator;
 * - LOGBOOK_VIEW (a user): the user, or a trainer.
 *
 * @extends Voter<string, Flight|FlightComment|User>
 */
final class FlightVoter extends Voter
{
    public const string VIEW = 'FLIGHT_VIEW';
    public const string PUBLISH = 'FLIGHT_PUBLISH';
    public const string DELETE = 'FLIGHT_DELETE';
    public const string COMMENT = 'FLIGHT_COMMENT';
    public const string COMMENT_DELETE = 'FLIGHT_COMMENT_DELETE';
    public const string LOGBOOK = 'LOGBOOK_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            self::VIEW, self::PUBLISH, self::DELETE, self::COMMENT => $subject instanceof Flight,
            self::COMMENT_DELETE => $subject instanceof FlightComment,
            self::LOGBOOK => $subject instanceof User,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (self::VIEW === $attribute && $subject instanceof Flight && $subject->isPublic()) {
            return true;
        }
        if (!$user instanceof User) {
            return false;
        }

        return match (true) {
            self::VIEW === $attribute => true,
            self::PUBLISH === $attribute && $subject instanceof Flight => $subject->getOwner() === $user,
            $subject instanceof Flight && self::DELETE === $attribute => $subject->getOwner() === $user || $user->hasAllPermissions(),
            $subject instanceof Flight => $subject->getOwner() === $user || $user->hasPermission(Permission::FlightReview),
            $subject instanceof FlightComment => $subject->getAuthor() === $user || $user->hasAllPermissions(),
            default => $subject === $user || $user->hasPermission(Permission::FlightReview),
        };
    }
}
