<?php

declare(strict_types=1);

namespace App\Flight;

use App\Entity\FlightComment;
use App\Push\PushMessage;
use App\Push\PushNotifier;
use App\Security\UserNotification;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * E-mails about flights: a comment written by someone else on a pilot's flight, when the pilot
 * accepts it ("Mon profil" > "Préférences e-mail"), and a notification to the pilot's devices that
 * want it.
 */
final readonly class FlightMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private PushNotifier $notifier,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function sendComment(FlightComment $comment): void
    {
        $pilot = $comment->getFlight()->getOwner();
        if (null === $pilot || $pilot === $comment->getAuthor()) {
            return;
        }
        $author = $comment->getAuthor()?->getDisplayName() ?? 'Un formateur';
        $this->notifier->later([$pilot], UserNotification::FlightComment, new PushMessage(
            \sprintf('%s a commenté votre vol', $author),
            mb_strimwidth($comment->getBody(), 0, 140, '…'),
            $this->urls->generate('app_flight_show', ['id' => $comment->getFlight()->getId(), '_fragment' => 'commentaires']),
            'flight-'.$comment->getFlight()->getId(),
        ));
        if (!$pilot->wants(UserNotification::FlightComment)) {
            return;
        }
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($pilot->getEmail(), $pilot->getDisplayName()))
            ->subject(\sprintf('%s a commenté votre vol', $author))
            ->htmlTemplate('email/flight_comment.html.twig')
            ->context(['comment' => $comment]));
    }
}
