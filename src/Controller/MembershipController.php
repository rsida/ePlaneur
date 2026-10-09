<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Membership;
use App\Entity\User;
use App\Form\MembershipRequestType;
use App\Membership\MembershipManager;
use App\Membership\MembershipStatus;
use App\Repository\MembershipRepository;
use App\Repository\MembershipSeasonRepository;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Membership of the club (roadmap step 8a): the fees of the open season for everyone; for a
 * logged-in account, its memberships, the request form and the certificate of a validated one.
 */
final class MembershipController extends AbstractController
{
    /** Fees of the open seasons and how to join, for visitors; a logged-in account goes to its space. */
    #[Route('/adhesion', name: 'app_membership', methods: ['GET'])]
    public function index(MembershipSeasonRepository $seasons): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_account_membership');
        }

        return $this->render('membership/index.html.twig', ['open' => $seasons->findOpen()]);
    }

    /** The account's memberships, the request form of the first open season it has not asked for. */
    #[Route('/mon-compte/adhesion', name: 'app_account_membership', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function account(Request $request, MembershipSeasonRepository $seasons, MembershipRepository $memberships, MembershipManager $manager): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $open = $seasons->findOpen();
        $season = null;
        foreach ($open as $candidate) {
            if (null === $memberships->findActive($user, $candidate) && !$candidate->getTypes()->isEmpty()) {
                $season = $candidate;
                break;
            }
        }
        $form = null;
        if (null !== $season) {
            $membership = $manager->prepare($user, $season);
            $form = $this->createForm(MembershipRequestType::class, $membership);
            $form->handleRequest($request);
            if ($form->isSubmitted() && null !== $membership->getType() && !$membership->getType()->allowsBirthDate($membership->getBirthDate())) {
                $form->get('type')->addError(new FormError(\sprintf('Ce tarif est réservé aux adhérents de %d ans au plus au 1er janvier %d.', (int) $membership->getType()->getMaxAge(), $season->getYear())));
            }
            if ($form->isSubmitted() && $form->isValid()) {
                $manager->request($membership);
                if ($manager->canPayOnline($membership)) {
                    return $this->redirect($manager->payOnline($membership, $this->paymentUrls()));
                }
                $this->addFlash('success', 'Votre demande d’adhésion est enregistrée : un e-mail récapitulatif vous a été envoyé.');

                return $this->redirectToRoute('app_account_membership');
            }
        }
        $mine = $memberships->findByUser($user);

        return $this->render('account/membership.html.twig', [
            'section' => 'membership',
            'open' => $open,
            'season' => $season,
            'form' => $form?->createView(),
            'memberships' => $mine,
            // Requests the member can pay online now
            'payable' => array_values(array_filter($mine, $manager->canPayOnline(...))),
            'online_payment' => $manager->isOnlinePaymentEnabled(),
        ], new Response(status: null !== $form && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Pays a request online (again): the member is sent to HelloAsso. */
    #[Route('/adhesion/{id<\d+>}/payer', name: 'app_membership_pay', methods: ['POST'])]
    public function pay(Membership $membership, Request $request, MembershipManager $manager): Response
    {
        if ($membership->getUser() !== $this->getUser() || !$this->isCsrfTokenValid('pay'.$membership->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$manager->canPayOnline($membership)) {
            return $this->redirectToRoute('app_account_membership');
        }

        return $this->redirect($manager->payOnline($membership, $this->paymentUrls()));
    }

    /** Back from HelloAsso: the payment is checked with HelloAsso before being recorded. */
    #[Route('/adhesion/paiement/retour', name: 'app_membership_payment_return', methods: ['GET'])]
    public function paymentReturn(Request $request, MembershipRepository $memberships, MembershipManager $manager): Response
    {
        $membership = $memberships->findOneBy(['checkoutIntentId' => $request->query->getString('checkoutIntentId')]);
        if (null === $membership || $membership->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException('Unknown payment.');
        }
        if ($manager->confirmOnlinePayment($membership)) {
            $this->addFlash('success', 'Merci ! Votre paiement est enregistré : le club valide votre adhésion dans les prochains jours.');
        } else {
            $this->addFlash('success', 'Votre paiement est en cours de traitement par HelloAsso : il apparaîtra ici dès qu’il sera confirmé.');
        }

        return $this->redirectToRoute('app_account_membership');
    }

    /** The member left the HelloAsso page, or the payment failed: they can try again. */
    #[Route('/adhesion/paiement/annule', name: 'app_membership_payment_cancelled', methods: ['GET'])]
    public function paymentCancelled(): Response
    {
        $this->addFlash('success', 'Le paiement n’a pas abouti. Votre demande est conservée : vous pouvez réessayer quand vous voulez.');

        return $this->redirectToRoute('app_account_membership');
    }

    /**
     * Notification of HelloAsso (to set in its back-office: Mon compte > Intégrations et API). Its
     * content is not trusted: only the membership id of its metadata is read, and the payment is
     * checked with HelloAsso.
     */
    #[Route('/adhesion/paiement/notification', name: 'app_membership_payment_notification', methods: ['POST'])]
    public function paymentNotification(Request $request, MembershipRepository $memberships, MembershipManager $manager): Response
    {
        $payload = json_decode($request->getContent(), true);
        $id = \is_array($payload) ? ($payload['metadata']['membership'] ?? null) : null;
        $membership = is_numeric($id) ? $memberships->find((int) $id) : null;
        if (null !== $membership) {
            $manager->confirmOnlinePayment($membership);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /** Certificate of a validated membership, to print: for its member and the club. */
    #[Route('/adhesion/{id<\d+>}/attestation', name: 'app_membership_certificate', methods: ['GET'])]
    public function certificate(Membership $membership): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || ($membership->getUser() !== $user && !$this->isGranted(Permission::MembershipManage->value))) {
            throw $this->createAccessDeniedException();
        }
        if (MembershipStatus::Validated !== $membership->getStatus()) {
            throw $this->createNotFoundException('Only a validated membership has a certificate.');
        }

        return $this->render('membership/certificate.html.twig', ['membership' => $membership]);
    }

    /**
     * @return array{return: string, back: string, error: string}
     */
    private function paymentUrls(): array
    {
        return [
            'return' => $this->generateUrl('app_membership_payment_return', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'back' => $this->generateUrl('app_membership_payment_cancelled', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'error' => $this->generateUrl('app_membership_payment_cancelled', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }
}
