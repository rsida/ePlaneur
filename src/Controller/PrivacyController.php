<?php

declare(strict_types=1);

namespace App\Controller;

use App\Account\AccountDeleter;
use App\Account\DataExporter;
use App\Entity\User;
use App\Form\Account\ProfileForms;
use App\Repository\FlightRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Confidentialité et données" (member space, GDPR): what the club keeps, the visibility choices,
 * the archive of the account's data, and the deletion of the account in two steps (what becomes of
 * the flights, then password and acknowledgement).
 */
final class PrivacyController extends AbstractController
{
    #[Route('/mon-compte/confidentialite', name: 'app_account_privacy', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function privacy(Request $request, ProfileForms $forms, EntityManagerInterface $entityManager, AccountDeleter $deleter): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $form = $forms->privacy(['flightsPublicByDefault' => $user->isFlightsPublicByDefault(), 'inRanking' => $user->isInRanking()]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setFlightsPublicByDefault((bool) $form->get('flightsPublicByDefault')->getData())->setInRanking((bool) $form->get('inRanking')->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Vos choix sont enregistrés.');

            return $this->redirectToRoute('app_account_privacy');
        }

        return $this->render('account/privacy.html.twig', [
            'section' => 'privacy',
            'form' => $form->createView(),
            'can_delete' => $deleter->canDelete($user),
        ]);
    }

    /** Archive of the account's data (ZIP), built on request. */
    #[Route('/mon-compte/donnees', name: 'app_account_export', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function export(Request $request, DataExporter $exporter): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        if (!$this->isCsrfTokenValid('export_data', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $response = new BinaryFileResponse($exporter->export($user));
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'mes-donnees-eplaneur.zip');
        $response->headers->set('Content-Type', 'application/zip');
        $response->deleteFileAfterSend();

        return $response;
    }

    /** Deletion, step 1: what will be deleted or kept, and the choice for the flights. */
    #[Route('/mon-compte/suppression', name: 'app_account_delete', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, ProfileForms $forms, FlightRepository $flights, AccountDeleter $deleter): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        if (!$deleter->canDelete($user)) {
            return $this->redirectToRoute('app_account_privacy');
        }
        $count = $flights->count(['owner' => $user]);
        $form = $forms->deletionChoice($count);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            return $this->redirectToRoute('app_account_delete_confirm', ['vols' => $form->get('keepFlights')->getData()]);
        }

        return $this->render('account/delete.html.twig', ['section' => 'privacy', 'form' => $form->createView(), 'flights' => $count]);
    }

    /** Deletion, step 2: password and acknowledgement, then the account is deleted and logged out. */
    #[Route('/mon-compte/suppression/confirmer', name: 'app_account_delete_confirm', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function confirm(Request $request, ProfileForms $forms, FlightRepository $flights, AccountDeleter $deleter, Security $security): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        if (!$deleter->canDelete($user)) {
            return $this->redirectToRoute('app_account_privacy');
        }
        $form = $forms->deletionConfirmation('1' === $request->query->getString('vols') ? '1' : '0');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $deleter->delete($user, '1' === $form->get('keepFlights')->getData());
            $security->logout(false);

            return $this->redirectToRoute('app_account_deleted');
        }

        return $this->render('account/delete_confirm.html.twig', [
            'section' => 'privacy',
            'form' => $form->createView(),
            'flights' => $flights->count(['owner' => $user]),
            'keep' => '1' === $form->get('keepFlights')->getData(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/compte-supprime', name: 'app_account_deleted', methods: ['GET'])]
    public function deleted(): Response
    {
        return $this->render('account/deleted.html.twig');
    }
}
