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

/**
 * Membership of the club (roadmap step 8a): the fees of the open season for everyone; for a
 * logged-in account, its memberships, the request form and the certificate of a validated one.
 */
final class MembershipController extends AbstractController
{
    #[Route('/adhesion', name: 'app_membership', methods: ['GET', 'POST'])]
    public function index(Request $request, MembershipSeasonRepository $seasons, MembershipRepository $memberships, MembershipManager $manager): Response
    {
        $open = $seasons->findOpen();
        $user = $this->getUser();
        $form = null;
        $season = null;
        if ($user instanceof User) {
            // The first open season the account has not asked for yet
            foreach ($open as $candidate) {
                if (null === $memberships->findActive($user, $candidate) && !$candidate->getTypes()->isEmpty()) {
                    $season = $candidate;
                    break;
                }
            }
        }
        if (null !== $season) {
            \assert($user instanceof User);
            $membership = $manager->prepare($user, $season);
            $form = $this->createForm(MembershipRequestType::class, $membership);
            $form->handleRequest($request);
            if ($form->isSubmitted() && null !== $membership->getType() && !$membership->getType()->allowsBirthDate($membership->getBirthDate())) {
                $form->get('type')->addError(new FormError(\sprintf('Ce tarif est réservé aux adhérents de %d ans au plus au 1er janvier %d.', (int) $membership->getType()->getMaxAge(), $season->getYear())));
            }
            if ($form->isSubmitted() && $form->isValid()) {
                $manager->request($membership);
                $this->addFlash('success', 'Votre demande d’adhésion est enregistrée : un e-mail récapitulatif vous a été envoyé.');

                return $this->redirectToRoute('app_membership');
            }
        }

        return $this->render('membership/index.html.twig', [
            'open' => $open,
            'season' => $season,
            'form' => $form?->createView(),
            'memberships' => $user instanceof User ? $memberships->findByUser($user) : [],
        ], new Response(status: null !== $form && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
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
}
