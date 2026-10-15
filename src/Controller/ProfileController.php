<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\Account\ProfileForms;
use App\Push\PushNotifier;
use App\Repository\UserRepository;
use App\Security\AccountMailer;
use App\Security\AccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

/**
 * "Mon profil" (member space): display name, e-mail address (changed after confirmation of the new
 * one), password, Condor identity (to recognise the pilot's tracks), e-mail preferences. One form
 * per panel; the panel that was sent shows its errors or its success.
 */
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/mon-compte/profil', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, ProfileForms $forms, EntityManagerInterface $entityManager, AccountManager $accountManager, AccountMailer $mailer, UserRepository $users, RateLimiterFactoryInterface $accountEmailLimiter, PushNotifier $notifier): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $panels = [
            'display_name' => $forms->displayName(['displayName' => $user->getDisplayName()]),
            'email_change' => $forms->email(),
            'password_change' => $forms->password(),
            'condor' => $forms->condor(['condorCn' => $user->getCondorCn(), 'condorPilotName' => $user->getCondorPilotName(), 'condorVersions' => $user->getCondorVersions()]),
            'notifications' => $forms->notifications(['notifications' => $user->getNotifications()]),
        ];
        $submitted = null;
        foreach ($panels as $name => $form) {
            $form->handleRequest($request);
            if (!$form->isSubmitted()) {
                continue;
            }
            $submitted = $name;
            if ($form->isValid() && $this->apply($name, $form, $user, $entityManager, $accountManager, $mailer, $users, $accountEmailLimiter, $request)) {
                return $this->redirectToRoute('app_account_profile', ['_fragment' => $name]);
            }
        }

        return $this->render('account/profile.html.twig', [
            'section' => 'profile',
            'forms' => array_map(static fn (FormInterface $form) => $form->createView(), $panels),
            'push_key' => $notifier->isEnabled() ? $notifier->getPublicKey() : null,
        ], new Response(status: null !== $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Link of the e-mail sent to the new address: the address changes once it is followed. */
    #[Route('/mon-compte/email/confirmer', name: 'app_account_email_confirm', methods: ['GET'])]
    public function confirmEmail(Request $request, AccountManager $accountManager, UserRepository $users): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $email = mb_strtolower($request->query->getString('email'));
        try {
            if (null !== $users->findOneByEmail($email)) {
                throw new \DomainException('Address already used.');
            }
            $accountManager->confirmEmailChange($request, $user, $email);
            $this->addFlash('success', \sprintf('Votre adresse e-mail est maintenant %s.', $email));
        } catch (VerifyEmailExceptionInterface|\DomainException) {
            $this->addFlash('error', 'Ce lien de confirmation n’est pas valide, a expiré, ou l’adresse est déjà utilisée par un autre compte. Recommencez depuis « Adresse e-mail ».');
        }

        return $this->redirectToRoute('app_account_profile', ['_fragment' => 'email_change']);
    }

    /**
     * @param FormInterface<array<string, mixed>> $form
     *
     * @return bool whether the change is done (else the form got an error)
     */
    private function apply(string $name, FormInterface $form, User $user, EntityManagerInterface $entityManager, AccountManager $accountManager, AccountMailer $mailer, UserRepository $users, RateLimiterFactoryInterface $limiter, Request $request): bool
    {
        $data = $form->getData();
        switch ($name) {
            case 'display_name':
                $user->setDisplayName((string) $data['displayName']);
                $this->addFlash('success', 'Votre nom affiché est enregistré.');
                break;
            case 'email_change':
                $email = mb_strtolower(trim((string) $data['email']));
                if ($email === $user->getEmail()) {
                    $form->get('email')->addError(new FormError('C’est déjà votre adresse.'));

                    return false;
                }
                if (null !== $users->findOneByEmail($email)) {
                    $form->get('email')->addError(new FormError('Cette adresse est déjà utilisée par un autre compte.'));

                    return false;
                }
                if (!$limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                    $form->addError(new FormError('Plusieurs e-mails ont déjà été envoyés : réessayez dans quelques minutes.'));

                    return false;
                }
                $mailer->sendEmailChange($user, $email);
                $this->addFlash('success', \sprintf('Un e-mail de confirmation est parti à %s : suivez son lien pour changer d’adresse.', $email));

                return true;
            case 'password_change':
                $accountManager->changePassword($user, (string) $data['plainPassword']);
                $this->addFlash('success', 'Votre mot de passe est modifié.');

                return true;
            case 'condor':
                $user->setCondorCn($data['condorCn'])->setCondorPilotName($data['condorPilotName'])->setCondorVersions($data['condorVersions'] ?? []);
                $this->addFlash('success', 'Votre identité Condor est enregistrée.');
                break;
            case 'notifications':
                $user->setNotifications($data['notifications'] ?? []);
                $this->addFlash('success', 'Vos préférences e-mail sont enregistrées.');
                break;
        }
        $entityManager->flush();

        return true;
    }
}
