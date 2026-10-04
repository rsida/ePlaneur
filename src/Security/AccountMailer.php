<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * E-mails about the account itself: address confirmation and password reset.
 */
final readonly class AccountMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private VerifyEmailHelperInterface $verifyEmailHelper,
    ) {
    }

    public function sendEmailConfirmation(User $user): void
    {
        $id = (string) $user->getId();
        $signature = $this->verifyEmailHelper->generateSignature('app_verify_email', $id, $user->getEmail(), ['id' => $id]);

        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Confirmez votre adresse e-mail')
            ->htmlTemplate('email/confirm_email.html.twig')
            ->context([
                'user' => $user,
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAt' => $signature->getExpiresAt(),
            ]));
    }

    public function sendPasswordReset(User $user, ResetPasswordToken $token): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject('Réinitialisez votre mot de passe')
            ->htmlTemplate('email/reset_password.html.twig')
            ->context([
                'user' => $user,
                'resetToken' => $token,
            ]));
    }
}
