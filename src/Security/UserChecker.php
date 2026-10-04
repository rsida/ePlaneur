<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses the login of accounts whose e-mail address has not been confirmed yet.
 */
final class UserChecker implements UserCheckerInterface
{
    public const string UNVERIFIED_MESSAGE = 'Confirmez d’abord votre adresse e-mail grâce au lien reçu lors de l’inscription.';

    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if ($user instanceof User && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(self::UNVERIFIED_MESSAGE);
        }
    }
}
