<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Account lifecycle: registration, e-mail confirmation, password change.
 */
final readonly class AccountManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private AccountMailer $mailer,
    ) {
    }

    /**
     * Creates a free account, not verified yet, and sends the confirmation link.
     */
    public function register(User $user, string $plainPassword): void
    {
        $this->changePassword($user, $plainPassword, flush: false);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->mailer->sendEmailConfirmation($user);
    }

    /**
     * Validates the signed link of the confirmation e-mail and activates the account.
     *
     * @throws VerifyEmailExceptionInterface when the link is invalid or expired
     */
    public function confirmEmail(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), $user->getEmail());

        $user->setVerified(true);
        $this->entityManager->flush();
    }

    public function changePassword(User $user, string $plainPassword, bool $flush = true): void
    {
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
