<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flight;
use App\Entity\FlightComment;
use App\Entity\User;
use App\Flight\Advice\FlightAdvisor;
use App\Flight\FlightComparison;
use App\Flight\FlightImporter;
use App\Flight\FlightSession;
use App\Flight\Reader\InvalidTrackException;
use App\Form\FlightCommentType;
use App\Repository\FlightRepository;
use App\Security\Voter\FlightVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Flight viewer (roadmap step 7): logged-in users upload their Condor flight tracks and see any
 * flight of the club on a map, in 3D, with its charts and analysis.
 */
#[IsGranted('ROLE_USER')]
#[Route('/vols', name: 'app_flight_')]
final class FlightController extends AbstractController
{
    private const string UPLOAD_TOKEN = 'flight_upload';

    /** Flights uploaded by the user, and the latest of the club; a track dropped here is analysed. */
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request, FlightRepository $flights, FlightImporter $importer, ValidatorInterface $validator): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $error = null;

        if ($request->isMethod('POST')) {
            $file = $request->files->get('trace');
            if (!$this->isCsrfTokenValid(self::UPLOAD_TOKEN, $request->request->getString('_token'))) {
                $error = 'La session a expiré : rechargez la page et recommencez.';
            } elseif (!$file instanceof UploadedFile) {
                $error = 'Choisissez un fichier de vol.';
            } else {
                $violations = $validator->validate($file, new Assert\File(maxSize: '20M', extensions: ['ftr', 'igc'], extensionsMessage: 'Déposez une trace Condor (.ftr) ou un fichier IGC.', maxSizeMessage: 'Le fichier dépasse 20 Mo.'));
                if (\count($violations) > 0) {
                    $error = (string) $violations[0]->getMessage();
                } else {
                    try {
                        $flight = $importer->import($file->getPathname(), $file->getClientOriginalName(), $user);

                        return $this->redirectToRoute('app_flight_show', ['id' => $flight->getId()]);
                    } catch (InvalidTrackException $exception) {
                        $error = $exception->getMessage();
                    }
                }
            }
        }

        return $this->render('flight/index.html.twig', [
            'mine' => $flights->findLatest(50, $user),
            'latest' => $flights->findLatest(20),
            'error' => $error,
            'upload_token' => self::UPLOAD_TOKEN,
        ], new Response(status: null !== $error ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** The flights of a pilot over time: totals, trends and the advice that comes back most. */
    #[Route('/carnet', name: 'logbook', methods: ['GET'])]
    public function logbook(FlightRepository $flights, FlightAdvisor $advisor): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->renderLogbook($user, $flights, $advisor);
    }

    #[Route('/carnet/{id<\d+>}', name: 'logbook_pilot', methods: ['GET'])]
    #[IsGranted(FlightVoter::LOGBOOK, 'pilot')]
    public function pilotLogbook(#[MapEntity(id: 'id')] User $pilot, FlightRepository $flights, FlightAdvisor $advisor): Response
    {
        return $this->renderLogbook($pilot, $flights, $advisor);
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    public function show(Flight $flight, FlightRepository $flights, FlightAdvisor $advisor): Response
    {
        return $this->renderFlight($flight, $flights, $advisor);
    }

    /** Comment of the pilot or a trainer, at the moment shown in the viewer or on the whole flight. */
    #[Route('/{id<\d+>}/commentaires', name: 'comment', methods: ['POST'])]
    #[IsGranted(FlightVoter::COMMENT, 'flight')]
    public function comment(Flight $flight, Request $request, FlightRepository $flights, FlightAdvisor $advisor, EntityManagerInterface $entityManager, ClockInterface $clock): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $comment = new FlightComment($flight, $user, $clock->now());
        $form = $this->createForm(FlightCommentType::class, $comment, ['action' => $this->generateUrl('app_flight_comment', ['id' => $flight->getId()])]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $count = \count($flight->getAnalysis()['series']['time']);
            if (!$form->get('atMoment')->getData() || null === $comment->getAt() || $comment->getAt() >= $count) {
                $comment->setAt(null);
            }
            $entityManager->persist($comment);
            $entityManager->flush();
            $this->addFlash('info', 'Commentaire ajouté.');

            return $this->redirectToRoute('app_flight_show', ['id' => $flight->getId(), '_fragment' => 'commentaires']);
        }

        return $this->renderFlight($flight, $flights, $advisor, $form);
    }

    #[Route('/commentaires/{id<\d+>}/supprimer', name: 'comment_delete', methods: ['POST'])]
    #[IsGranted(FlightVoter::COMMENT_DELETE, 'comment')]
    public function deleteComment(FlightComment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $flight = $comment->getFlight();
        if ($this->isCsrfTokenValid('delete_comment_'.$comment->getId(), $request->request->getString('_token'))) {
            $entityManager->remove($comment);
            $entityManager->flush();
            $this->addFlash('info', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('app_flight_show', ['id' => $flight->getId(), '_fragment' => 'commentaires']);
    }

    private function renderLogbook(User $pilot, FlightRepository $flights, FlightAdvisor $advisor): Response
    {
        $logbook = $flights->findLogbook($pilot);
        $points = [];
        $tips = [];
        foreach ($logbook as $flight) {
            $statistics = $flight->getAnalysis()['statistics'];
            $points[] = [
                'url' => $this->generateUrl('app_flight_show', ['id' => $flight->getId()]),
                'date' => $flight->getDate()->format('d/m/Y'),
                'plan' => $flight->getFlightPlan() ?? $flight->getOriginalName(),
                'taskSpeed' => $flight->getTaskSpeed(),
                'climb' => $statistics['climbAverage'] ?? null,
                'glideRatio' => $statistics['glideRatio'] ?? null,
                'circling' => null !== ($statistics['circlingShare'] ?? null) ? round($statistics['circlingShare'] * 100) : null,
            ];
            foreach ($advisor->advise($flight->getAnalysis()) as $advice) {
                if ('tip' === $advice->kind) {
                    $tips[$advice->title] ??= ['title' => $advice->title, 'step' => $advice->step, 'count' => 0];
                    ++$tips[$advice->title]['count'];
                }
            }
        }
        usort($tips, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $this->render('flight/logbook.html.twig', [
            'pilot' => $pilot,
            'own' => $pilot === $this->getUser(),
            'flights' => $logbook,
            'points' => $points,
            'tips' => $tips,
            'hours' => array_sum(array_map(static fn (Flight $flight): int => $flight->getDuration(), $logbook)),
            'distance' => array_sum(array_map(static fn (Flight $flight): int => $flight->getDistance(), $logbook)),
            'tasks' => \count(array_filter($logbook, static fn (Flight $flight): bool => null !== $flight->getTaskSpeed())),
        ]);
    }

    /**
     * @param FormInterface<FlightComment>|null $form the comment form, with its errors
     */
    private function renderFlight(Flight $flight, FlightRepository $flights, FlightAdvisor $advisor, ?FormInterface $form = null): Response
    {
        if (null === $form && $this->isGranted(FlightVoter::COMMENT, $flight)) {
            $user = $this->getUser();
            \assert($user instanceof User);
            $form = $this->createForm(FlightCommentType::class, new FlightComment($flight, $user, new \DateTimeImmutable()), ['action' => $this->generateUrl('app_flight_comment', ['id' => $flight->getId()])]);
        }

        return $this->render('flight/show.html.twig', [
            'flight' => $flight,
            'session_size' => $flights->countSession($flight),
            'advice' => $advisor->advise($flight->getAnalysis()),
            'comment_form' => $form?->createView(),
        ], new Response(status: null !== $form && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /** Every flight on the flight plan of this one: ranking, podium and replay of all pilots. */
    #[Route('/{id<\d+>}/session', name: 'session', methods: ['GET'])]
    public function session(Flight $flight, FlightRepository $flights): Response
    {
        return $this->render('flight/session.html.twig', ['flight' => $flight, 'session' => FlightSession::of($flights->findSession($flight))]);
    }

    /**
     * What the session replay draws: the task and, for each flight, its track (one point per second
     * from `start`, seconds since the beginning of the recording) and the time it started the task.
     */
    #[Route('/{id<\d+>}/session.json', name: 'session_data', methods: ['GET'])]
    public function sessionData(Flight $flight, FlightRepository $flights): JsonResponse
    {
        $session = FlightSession::of($flights->findSession($flight));
        $task = null;
        $tracks = [];
        foreach ($session->flights as $member) {
            $analysis = $member->getAnalysis();
            $task ??= $analysis['task'] ?? null;
            $time = $analysis['series']['time'];
            $started = $analysis['task']['turnpoints'][1]['reached'] ?? null;
            $tracks[] = [
                'id' => $member->getId(),
                'pilot' => $member->getPilot(),
                'competitionId' => $member->getCompetitionId(),
                'start' => $time[0] ?? 0,
                'taskStart' => null !== $started ? $time[$started] : null,
                'latitude' => $analysis['series']['latitude'],
                'longitude' => $analysis['series']['longitude'],
                'altitude' => $analysis['series']['altitude'],
            ];
        }
        $response = new JsonResponse(['task' => $task, 'flights' => $tracks]);
        $response->setPrivate();
        $response->setMaxAge(300);

        return $response;
    }

    /** What the viewer draws: 1 s series, phases, statistics and task (FlightAnalysis::toArray()). */
    #[Route('/{id<\d+>}/analyse.json', name: 'analysis', methods: ['GET'])]
    public function analysis(Flight $flight): JsonResponse
    {
        $response = new JsonResponse($flight->getAnalysis());
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * Comparison with the flight chosen in the form (`?avec=`), by default the best other flight of
     * the session.
     */
    #[Route('/{id<\d+>}/comparer', name: 'compare_pick', methods: ['GET'])]
    public function comparePick(Flight $flight, Request $request, FlightRepository $flights): Response
    {
        $others = array_values(array_filter(FlightSession::of($flights->findSession($flight))->flights, static fn (Flight $other): bool => $other->getId() !== $flight->getId()));
        $chosen = $request->query->getInt('avec');
        $reference = array_find($others, static fn (Flight $other): bool => $other->getId() === $chosen) ?? $others[0] ?? null;
        if (null === $reference) {
            $this->addFlash('info', 'Aucun autre vol n’est enregistré sur ce plan de vol : rien à comparer pour l’instant.');

            return $this->redirectToRoute('app_flight_show', ['id' => $flight->getId()]);
        }

        return $this->redirectToRoute('app_flight_compare', ['id' => $flight->getId(), 'other' => $reference->getId()]);
    }

    /** Where along the task the flight gains or loses time on another flight of the same task. */
    #[Route('/{id<\d+>}/comparer/{other<\d+>}', name: 'compare', methods: ['GET'])]
    public function compare(Flight $flight, #[MapEntity(id: 'other')] Flight $reference, FlightRepository $flights): Response
    {
        $comparison = FlightComparison::of($flight, $reference);
        if (null === $comparison) {
            $this->addFlash('info', 'Ces deux vols ne peuvent pas être comparés : ils doivent suivre le même plan de vol et avoir passé la ligne de départ.');

            return $this->redirectToRoute('app_flight_show', ['id' => $flight->getId()]);
        }

        return $this->render('flight/compare.html.twig', [
            'flight' => $flight,
            'reference' => $reference,
            'comparison' => $comparison,
            'session' => FlightSession::of($flights->findSession($flight)),
        ]);
    }

    /**
     * What the comparison viewer draws: the task, both tracks, and the index in each track where
     * the task distance reaches each FlightComparison::STEP.
     */
    #[Route('/{id<\d+>}/comparer/{other<\d+>}.json', name: 'compare_data', methods: ['GET'])]
    public function compareData(Flight $flight, #[MapEntity(id: 'other')] Flight $reference): JsonResponse
    {
        $comparison = FlightComparison::of($flight, $reference) ?? throw $this->createNotFoundException();
        $tracks = [];
        foreach ([$flight, $reference] as $k => $member) {
            $analysis = $member->getAnalysis();
            $tracks[] = [
                'pilot' => $member->getPilot(),
                'competitionId' => $member->getCompetitionId(),
                'start' => $comparison->starts[$k],
                'times' => $comparison->times[$k],
                'latitude' => $analysis['series']['latitude'],
                'longitude' => $analysis['series']['longitude'],
                'altitude' => $analysis['series']['altitude'],
            ];
        }
        $response = new JsonResponse(['task' => $flight->getAnalysis()['task'], 'step' => FlightComparison::STEP, 'flights' => $tracks]);
        $response->setPrivate();
        $response->setMaxAge(300);

        return $response;
    }

    #[Route('/{id<\d+>}/supprimer', name: 'delete', methods: ['POST'])]
    #[IsGranted(FlightVoter::DELETE, 'flight')]
    public function delete(Flight $flight, Request $request, FlightImporter $importer): Response
    {
        if ($this->isCsrfTokenValid('delete_flight_'.$flight->getId(), $request->request->getString('_token'))) {
            $importer->delete($flight);
            $this->addFlash('success', 'Le vol a été supprimé.');
        }

        return $this->redirectToRoute('app_flight_index');
    }
}
