<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Training\FirstFlight;
use App\Training\FirstFlightStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Mon premier vol" (roadmap 11a, doc/training-path.md#mon-premier-vol): the way from discovering
 * the club to the first network flight, as a checklist kept in the account.
 */
#[Route('/premier-vol', name: 'app_first_flight')]
final class FirstFlightController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(FirstFlight $firstFlight): Response
    {
        $user = $this->getUser();
        $done = $firstFlight->of($user instanceof User ? $user : null);

        return $this->render('first_flight/index.html.twig', [
            'steps' => FirstFlightStep::cases(),
            'done' => $done,
            'count' => \count(array_filter($done)),
            'next' => array_find(FirstFlightStep::cases(), static fn (FirstFlightStep $step): bool => !$done[$step->value]),
            'initiation' => $firstFlight->nextInitiation(),
        ]);
    }

    /** A step the site cannot see, said done (or not done any more) by the pilot. */
    #[Route('/{step}', name: '_declare', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function declare(string $step, Request $request, EntityManagerInterface $entityManager): Response
    {
        $step = FirstFlightStep::tryFrom($step);
        $user = $this->getUser();
        if (null === $step || !$step->isDeclared() || !$user instanceof User) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('first-flight-'.$step->value, $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $user->declare($step, $request->getPayload()->getBoolean('done'));
        $entityManager->flush();

        return $this->redirectToRoute('app_first_flight', ['_fragment' => $step->value], Response::HTTP_SEE_OTHER);
    }
}
