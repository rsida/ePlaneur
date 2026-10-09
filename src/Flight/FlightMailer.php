<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\FlightComment;
use App\Security\UserNotification;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mails about flights: a comment written by someone else on a pilot's flight, when the pilot
 * accepts it ("Mon profil" > "Préférences e-mail").
 */
final readonly class FlightMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    public function sendComment(FlightComment $comment): void
    {
        $pilot = $comment->getFlight()->getOwner();
        if (null === $pilot || $pilot === $comment->getAuthor() || !$pilot->wants(UserNotification::FlightComment)) {
            return;
        }
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($pilot->getEmail(), $pilot->getDisplayName()))
            ->subject(\sprintf('%s a commenté votre vol', $comment->getAuthor()?->getDisplayName() ?? 'Un formateur'))
            ->htmlTemplate('email/flight_comment.html.twig')
            ->context(['comment' => $comment]));
    }
}
