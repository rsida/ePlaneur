<?php

declare(strict_types=1);

namespace App\Membership;

use App\Entity\Membership;
use App\Entity\MembershipSeason;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mails of the membership: request received, membership validated, renewal reminder.
 */
final readonly class MembershipMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    public function sendRequested(Membership $membership): void
    {
        $this->send($membership, \sprintf('Votre demande d’adhésion %d', $membership->getSeason()->getYear()), 'email/membership_requested.html.twig', ['membership' => $membership]);
    }

    public function sendValidated(Membership $membership): void
    {
        $this->send($membership, \sprintf('Bienvenue au Club ePlaneur pour %d !', $membership->getSeason()->getYear()), 'email/membership_validated.html.twig', ['membership' => $membership]);
    }

    public function sendReminder(Membership $past, MembershipSeason $season): void
    {
        $this->send($past, \sprintf('Renouvelez votre adhésion pour %d', $season->getYear()), 'email/membership_reminder.html.twig', ['membership' => $past, 'season' => $season]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(Membership $membership, string $subject, string $template, array $context): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($membership->getUser()?->getEmail() ?? $membership->getEmail(), $membership->getFullName()))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context));
    }
}
