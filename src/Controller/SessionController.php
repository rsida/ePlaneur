<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\NetworkSession;
use App\Entity\User;
use App\Flight\FlightSession;
use App\Form\SessionType;
use App\Repository\NetworkSessionRepository;
use App\Security\Permission;
use App\Security\Voter\SessionVoter;
use App\Session\InvalidSessionException;
use App\Session\SessionData;
use App\Session\SessionManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Network sessions: the coming ones and the history (public), a session with its results and the
 * tracks of its pilots (results and tracks for logged-in users), proposing and editing sessions.
 */
#[Route('/sessions', name: 'app_session_')]
final class SessionController extends AbstractController
{
    private const int PER_PAGE = 20;

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, NetworkSessionRepository $sessions, ClockInterface $clock): Response
    {
        $filters = array_filter([
            'annee' => $request->query->getString('annee'),
            'condor' => $request->query->getString('condor'),
            'categorie' => $request->query->getString('categorie'),
            'type' => $request->query->getString('type'),
        ], static fn (string $value): bool => '' !== $value);
        $page = max(1, $request->query->getInt('page', 1));
        $now = $clock->now();
        $past = $sessions->findPast($now, $filters, $page, self::PER_PAGE);

        return $this->render('session/index.html.twig', [
            'upcoming' => $sessions->findUpcoming($now, 10),
            'past' => $past['sessions'],
            'total' => $past['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($past['total'] / self::PER_PAGE)),
            'filters' => $filters,
            'years' => $sessions->years(),
            'dedicated' => $sessions->dedicatedLabels(),
        ]);
    }

    #[Route('/nouvelle', name: 'new', methods: ['GET', 'POST'])]
    #[IsGranted(Permission::SessionOrganise->value)]
    public function new(Request $request, SessionManager $manager, EntityManagerInterface $entityManager): Response
    {
        $data = new SessionData();
        if ($request->query->has('plan')) {
            $data->flightPlan = $request->query->getString('plan');
        }

        return $this->edit($request, $data, null, $manager, $entityManager);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(NetworkSession $session, ClockInterface $clock): Response
    {
        $flights = $session->getFlights()->toArray();
        $tracks = [];
        foreach ($flights as $flight) {
            if (null !== $flight->getCompetitionId()) {
                $tracks[mb_strtolower($flight->getCompetitionId())] = $flight;
            }
        }

        return $this->render('session/show.html.twig', [
            'session' => $session,
            'ranked' => [] !== $flights ? FlightSession::of(array_values($flights)) : null,
            'tracks' => $tracks,
            'past' => $session->isPast($clock->now()),
        ]);
    }

    /** Replay data of the tracks of the session (FlightSession::replayData()). */
    #[Route('/{id<\d+>}/traces.json', name: 'data', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function data(NetworkSession $session): JsonResponse
    {
        $flights = array_values($session->getFlights()->toArray());
        $response = new JsonResponse([] !== $flights ? FlightSession::of($flights)->replayData() : ['task' => null, 'flights' => []]);
        $response->setPrivate();
        $response->setMaxAge(300);

        return $response;
    }

    #[Route('/{id<\d+>}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    #[IsGranted(SessionVoter::EDIT, 'session')]
    public function editSession(Request $request, NetworkSession $session, SessionManager $manager, EntityManagerInterface $entityManager): Response
    {
        return $this->edit($request, SessionData::of($session), $session, $manager, $entityManager);
    }

    #[Route('/{id<\d+>}/supprimer', name: 'delete', methods: ['POST'])]
    #[IsGranted(SessionVoter::EDIT, 'session')]
    public function delete(Request $request, NetworkSession $session, SessionManager $manager): Response
    {
        if (!$this->isCsrfTokenValid('delete-session-'.$session->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $manager->delete($session);
        $this->addFlash('success', 'La session est supprimée. Les traces de ses pilotes restent dans leurs vols.');

        return $this->redirectToRoute('app_session_index');
    }

    private function edit(Request $request, SessionData $data, ?NetworkSession $session, SessionManager $manager, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SessionType::class, $data, ['with_results' => null !== $session]);
        $previousLink = $data->condorClubUrl;
        $form->handleRequest($request);
        $linkChanged = $data->condorClubUrl !== $previousLink;
        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            try {
                $saved = $manager->save($data, $session, $user instanceof User ? $user : null);
                // A new Condor Club link gives the settings of the task, unless a .fpl was sent
                if (null === $data->flightPlanFile && null !== $saved->getCondorClubTaskId() && ($linkChanged || null === $saved->getTaskSettings())) {
                    if ($manager->fetchSettings($saved)) {
                        $entityManager->flush();
                    } else {
                        $this->addFlash('error', 'Les réglages du circuit n’ont pas pu être lus sur Condor Club : vous pouvez déposer le fichier .fpl de la session.');
                    }
                }
                $this->addFlash('success', null === $session ? 'La session est proposée : elle apparaît dans les prochaines sessions.' : 'La session est enregistrée.');

                return $this->redirectToRoute('app_session_show', ['id' => $saved->getId()]);
            } catch (InvalidSessionException $exception) {
                $form->get($exception->field)->addError(new FormError($exception->getMessage()));
            }
        }

        return $this->render('session/edit.html.twig', ['form' => $form, 'session' => $session], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
