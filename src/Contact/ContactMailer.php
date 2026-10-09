<?php

declare(strict_types=1);

namespace App\Contact;

use App\Entity\ContactRequest;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Tells the club that a message arrived through the contact form; answering the e-mail replies to
 * the sender.
 */
final readonly class ContactMailer
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(CONTACT_EMAIL)%')]
        private string $clubEmail,
    ) {
    }

    public function notifyClub(ContactRequest $request): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to($this->clubEmail)
            ->replyTo(new Address($request->getEmail(), $request->getName()))
            ->subject(\sprintf('[Contact · %s] Message de %s', $request->getSubject()?->label() ?? 'Autre', $request->getName()))
            ->htmlTemplate('email/contact_request.html.twig')
            ->context(['request' => $request]));
    }
}
