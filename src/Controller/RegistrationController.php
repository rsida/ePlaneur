<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountEmailFormType;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\AccountMailer;
use App\Security\AccountManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, AccountManager $accountManager): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_account');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $accountManager->register($user, $plainPassword);

            return $this->redirectToRoute('app_register_check_email');
        }

        return $this->render('registration/register.html.twig', ['form' => $form]);
    }

    #[Route('/inscription/verifiez-vos-e-mails', name: 'app_register_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        return $this->render('registration/check_email.html.twig');
    }

    #[Route('/inscription/confirmer', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request, UserRepository $users, AccountManager $accountManager): Response
    {
        $user = $users->find($request->query->getInt('id'));
        if (null === $user) {
            $this->addFlash('error', 'Ce lien de confirmation n’est pas valide.');

            return $this->redirectToRoute('app_register_resend');
        }

        if (!$user->isVerified()) {
            try {
                $accountManager->confirmEmail($request, $user);
            } catch (VerifyEmailExceptionInterface) {
                $this->addFlash('error', 'Ce lien de confirmation n’est pas valide ou a expiré. Demandez-en un nouveau.');

                return $this->redirectToRoute('app_register_resend');
            }
        }

        $this->addFlash('success', 'Votre adresse e-mail est confirmée. Vous pouvez vous connecter.');

        return $this->redirectToRoute('app_login');
    }

    /**
     * Sends a new confirmation link. The answer is the same whether the account exists or not, so
     * the page cannot be used to find out who is registered.
     */
    #[Route('/inscription/renvoyer-confirmation', name: 'app_register_resend', methods: ['GET', 'POST'])]
    public function resend(Request $request, UserRepository $users, AccountMailer $mailer, RateLimiterFactoryInterface $accountEmailLimiter): Response
    {
        $form = $this->createForm(AccountEmailFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();
            $user = $users->findOneByEmail($email);
            $allowed = $accountEmailLimiter->create($request->getClientIp())->consume()->isAccepted();
            if ($allowed && null !== $user && !$user->isVerified()) {
                $mailer->sendEmailConfirmation($user);
            }

            return $this->redirectToRoute('app_register_check_email');
        }

        return $this->render('registration/resend.html.twig', ['form' => $form]);
    }
}
