<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountEmailFormType;
use App\Form\ChangePasswordFormType;
use App\Repository\UserRepository;
use App\Security\AccountMailer;
use App\Security\AccountManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Forgotten password: request a link by e-mail, then choose a new password.
 */
#[Route('/mot-de-passe-oublie')]
final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, UserRepository $users, AccountMailer $mailer): Response
    {
        $form = $this->createForm(AccountEmailFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();

            return $this->sendResetLink($users->findOneByEmail($email), $mailer);
        }

        return $this->render('reset_password/request.html.twig', ['form' => $form]);
    }

    #[Route('/verifiez-vos-e-mails', name: 'app_check_email', methods: ['GET'])]
    public function checkEmail(): Response
    {
        // A fake token keeps the page identical whether the account exists or not
        $resetToken = $this->getTokenObjectFromSession() ?? $this->resetPasswordHelper->generateFakeResetToken();

        return $this->render('reset_password/check_email.html.twig', ['resetToken' => $resetToken]);
    }

    #[Route('/nouveau/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(Request $request, AccountManager $accountManager, ?string $token = null): Response
    {
        if (null !== $token) {
            // Keep the token out of the URL (and of the browser history, referrers...)
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('Aucun lien de réinitialisation en cours.');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            $this->addFlash('error', 'Ce lien de réinitialisation n’est pas valide ou a expiré. Demandez-en un nouveau.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->resetPasswordHelper->removeResetRequest($token);
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            // Receiving the link proves the address belongs to the user
            $user->setVerified(true);
            $accountManager->changePassword($user, $plainPassword);
            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Votre mot de passe est modifié. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', ['form' => $form]);
    }

    private function sendResetLink(?User $user, AccountMailer $mailer): RedirectResponse
    {
        // Same answer whether the account exists or not: the page must not reveal who is registered
        if (null === $user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            // Throttled (a request is already pending): answer as if it had been sent
            return $this->redirectToRoute('app_check_email');
        }

        $mailer->sendPasswordReset($user, $resetToken);
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
