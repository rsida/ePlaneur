<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contact\ContactMailer;
use App\Entity\ContactRequest;
use App\Entity\User;
use App\Form\ContactType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contact form (roadmap step 9b): for everyone at /contact, in the member space for a logged-in
 * account (name and e-mail filled in). Each message is kept for the back-office and e-mailed to the
 * club. A hidden field and a rate limit keep robots out.
 */
final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request, EntityManagerInterface $entityManager, ContactMailer $mailer, ClockInterface $clock, RateLimiterFactoryInterface $contactLimiter): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_account_contact');
        }

        return $this->handle('contact/index.html.twig', 'app_contact', $request, $entityManager, $mailer, $clock, $contactLimiter);
    }

    #[Route('/mon-compte/contact', name: 'app_account_contact', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function accountContact(Request $request, EntityManagerInterface $entityManager, ContactMailer $mailer, ClockInterface $clock, RateLimiterFactoryInterface $contactLimiter): Response
    {
        return $this->handle('account/contact.html.twig', 'app_account_contact', $request, $entityManager, $mailer, $clock, $contactLimiter);
    }

    private function handle(string $template, string $route, Request $request, EntityManagerInterface $entityManager, ContactMailer $mailer, ClockInterface $clock, RateLimiterFactoryInterface $limiter): Response
    {
        $user = $this->getUser();
        $contact = new ContactRequest($clock->now(), $user instanceof User ? $user : null);
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // A robot fills in every field: pretend it worked
            if ('' !== (string) $form->get('website')->getData()) {
                return $this->redirectToRoute($route, ['envoye' => 1]);
            }
            if (!$limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $form->addError(new FormError('Vous avez envoyé plusieurs messages en peu de temps : réessayez dans une heure, ou écrivez directement à l’adresse du club.'));
            } else {
                $entityManager->persist($contact);
                $entityManager->flush();
                $mailer->notifyClub($contact);

                return $this->redirectToRoute($route, ['envoye' => 1]);
            }
        }

        return $this->render($template, [
            'form' => $form->createView(),
            'sent' => $request->query->getBoolean('envoye'),
            'section' => 'contact',
        ], new Response(status: $form->isSubmitted() && !$form->isValid() || \count($form->getErrors()) > 0 ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
