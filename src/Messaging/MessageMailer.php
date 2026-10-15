<?php

declare(strict_types=1);

namespace App\Messaging;

use App\Entity\Message;
use App\Entity\User;
use App\Push\PushMessage;
use App\Push\PushNotifier;
use App\Security\UserNotification;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * E-mail about a new message to the readers of its conversation who accept it ("Mon profil" >
 * "Préférences e-mail"), and a notification to their devices that want it. The e-mail gives the
 * message; answers are written on the site.
 */
final readonly class MessageMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private PushNotifier $notifier,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param list<User> $readers
     */
    public function send(Message $message, array $readers): void
    {
        $author = $message->getAuthor()?->getDisplayName() ?? 'un membre';
        $this->notifier->later($readers, UserNotification::Message, new PushMessage(
            \sprintf('Message de %s', $author),
            \sprintf('%s : %s', $message->getConversation()->getSubject(), mb_strimwidth($message->getBody(), 0, 140, '…')),
            $this->urls->generate('app_account_message_show', ['id' => $message->getConversation()->getId()]),
            'conversation-'.$message->getConversation()->getId(),
        ));
        foreach ($readers as $reader) {
            if (!$reader->wants(UserNotification::Message)) {
                continue;
            }
            $this->mailer->send((new TemplatedEmail())
                ->to(new Address($reader->getEmail(), $reader->getDisplayName()))
                ->subject(\sprintf('Message de %s : %s', $author, $message->getConversation()->getSubject()))
                ->htmlTemplate('email/message.html.twig')
                ->context(['message' => $message, 'reader' => $reader]));
        }
    }
}
