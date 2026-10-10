<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Entity\Message;
use App\Entity\User;
use App\Security\UserNotification;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * E-mail about a new message to the readers of its conversation who accept it ("Mon profil" >
 * "Préférences e-mail"). The e-mail gives the message; answers are written on the site.
 */
final readonly class MessageMailer
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    /**
     * @param list<User> $readers
     */
    public function send(Message $message, array $readers): void
    {
        foreach ($readers as $reader) {
            if (!$reader->wants(UserNotification::Message)) {
                continue;
            }
            $this->mailer->send((new TemplatedEmail())
                ->to(new Address($reader->getEmail(), $reader->getDisplayName()))
                ->subject(\sprintf('Message de %s : %s', $message->getAuthor()?->getDisplayName() ?? 'un membre', $message->getConversation()->getSubject()))
                ->htmlTemplate('email/message.html.twig')
                ->context(['message' => $message, 'reader' => $reader]));
        }
    }
}
