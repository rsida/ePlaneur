<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Flight;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * FLIGHT_DELETE: the pilot who uploaded the flight, or an administrator.
 *
 * @extends Voter<string, Flight>
 */
final class FlightVoter extends Voter
{
    public const string DELETE = 'FLIGHT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof Flight;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && ($subject->getOwner() === $user || $user->hasAllPermissions());
    }
}
