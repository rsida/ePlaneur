<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flight;
use App\Entity\FlightComment;
use App\Entity\FlightPlan;
use App\Entity\User;
use App\Flight\Advice\FlightAdvisor;
use App\Flight\Club\ClubRecords;
use App\Flight\FlightComparison;
use App\Flight\FlightImporter;
use App\Flight\FlightMailer;
use App\Flight\FlightSession;
use App\Flight\Glider\GliderPerformance;
use App\Flight\IgcExporter;
use App\Flight\Logbook;
use App\Flight\Reader\InvalidTrackException;
use App\Form\FlightCommentType;
use App\Repository\FlightPlanRepository;
use App\Repository\FlightRepository;
use App\Repository\GliderRepository;
use App\Repository\StepProgressRepository;
use App\Security\Voter\FlightVoter;
use App\Training\TrainingPath;
use App\Training\TrainingStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Flight viewer (roadmap step 7): logged-in users upload their Condor flight tracks and see any
 * flight of the club on a map, in 3D, with its charts and analysis. Every page needs an account,
 * except the flights their pilots made public (FLIGHT_VIEW).
 */
#[Route('/vols', name: 'app_flight_')]
final class FlightController extends AbstractController
{
    public function __construct(
        private readonly GliderRepository $gliders,
    ) {
    }

    private const string UPLOAD_TOKEN = 'flight_upload';

    /** Flights uploaded by the user, and the latest of the club; a track dropped here is analysed. */
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
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

    /** Records of the club per task and per landscape, and the ranking of a season (`?saison=`). */
    #[Route('/records', name: 'records', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function records(Request $request, FlightRepository $flights, ClockInterface $clock): Response
    {
        $all = $flights->findAll();
        $seasons = ClubRecords::seasons($all);
        $season = $request->query->getInt('saison') ?: ($seasons[0] ?? (int) $clock->now()->format('Y'));

        return $this->render('flight/records.html.twig', [
            'tasks' => ClubRecords::byTask($all),
            'landscapes' => ClubRecords::byLandscape($all),
            'ranking' => ClubRecords::season($all, $season),
            'season' => $season,
            'seasons' => $seasons,
        ]);
    }

    /** The pilot's own logbook lives in the member space. */
    #[Route('/carnet', name: 'logbook', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function logbook(): Response
    {
        return $this->redirectToRoute('app_account_logbook', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/carnet/{id<\d+>}', name: 'logbook_pilot', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(FlightVoter::LOGBOOK, 'pilot')]
    public function pilotLogbook(#[MapEntity(id: 'id')] User $pilot, Logbook $logbook, TrainingPath $training, StepProgressRepository $progress, FormFactoryInterface $formFactory): Response
    {
        if ($pilot === $this->getUser()) {
            return $this->redirectToRoute('app_account_logbook');
        }
        $forms = $this->isGranted(FlightVoter::TRAINING, $pilot)
            ? TrainingController::forms($pilot, $progress, $formFactory, fn (TrainingStep $step): string => $this->generateUrl('app_training_step', ['id' => $pilot->getId(), 'step' => $step->value]))
            : [];

        return $this->render('flight/logbook.html.twig', [
            'pilot' => $pilot,
            'own' => false,
            'path' => $training->of($pilot),
            'training_forms' => array_map(static fn (FormInterface $form): FormView => $form->createView(), $forms),
        ] + $logbook->of($pilot));
    }

    #[Route('/{id<\d+>}', name: 'show', methods: ['GET'])]
    #[IsGranted(FlightVoter::VIEW, 'flight')]
    public function show(Flight $flight, FlightRepository $flights, FlightAdvisor $advisor): Response
    {
        return $this->renderFlight($flight, $flights, $advisor);
    }

    /** Comment of the pilot or a trainer, at the moment shown in the viewer or on the whole flight. */
    #[Route('/{id<\d+>}/commentaires', name: 'comment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(FlightVoter::COMMENT, 'flight')]
    public function comment(Flight $flight, Request $request, FlightRepository $flights, FlightAdvisor $advisor, EntityManagerInterface $entityManager, ClockInterface $clock, FlightMailer $mailer): Response
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
            $mailer->sendComment($comment);
            $this->addFlash('info', 'Commentaire ajouté.');

            return $this->redirectToRoute('app_flight_show', ['id' => $flight->getId(), '_fragment' => 'commentaires']);
        }

        return $this->renderFlight($flight, $flights, $advisor, $form);
    }

    #[Route('/commentaires/{id<\d+>}/supprimer', name: 'comment_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
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

    /**
     * @param FormInterface<FlightComment>|null $form the comment form, with its errors
     */
    private function renderFlight(Flight $flight, FlightRepository $flights, FlightAdvisor $advisor, ?FormInterface $form = null): Response
    {
        $glider = $this->gliders->findOneByName($flight->getGlider());
        $performance = null !== $glider ? GliderPerformance::of($glider, $flight->getAnalysis()) : null;
        if (null === $form && $this->isGranted(FlightVoter::COMMENT, $flight)) {
            $user = $this->getUser();
            \assert($user instanceof User);
            $form = $this->createForm(FlightCommentType::class, new FlightComment($flight, $user, new \DateTimeImmutable()), ['action' => $this->generateUrl('app_flight_comment', ['id' => $flight->getId()])]);
        }

        // Rank among the tracks of its race, and the winner to compare with
        $race = null;
        $session = $flight->getSession();
        if (null !== $session && $session->getFlights()->count() > 1) {
            $ranked = FlightSession::of(array_values($session->getFlights()->toArray()));
            $race = $ranked->ranks($flight) + ['winner' => $ranked->flights[0]];
        }

        return $this->render('flight/show.html.twig', [
            'flight' => $flight,
            'race' => $race,
            'session_size' => $flights->countSession($flight),
            'advice' => $advisor->advise($flight->getAnalysis(), $flight->getSession()?->getTaskSettings(), $performance),
            'performance' => $performance,
            'comment_form' => $form?->createView(),
        ], new Response(status: null !== $form && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * Every flight on the flight plan of this one: now the page of its network session, or of its
     * flight plan when it joined none.
     */
    #[Route('/{id<\d+>}/session', name: 'session', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function session(Flight $flight, FlightRepository $flights, FlightPlanRepository $plans): Response
    {
        if (null !== $flight->getSession()) {
            return $this->redirectToRoute('app_session_show', ['id' => $flight->getSession()->getId()]);
        }
        $code = FlightPlan::codeIn($flight->getFlightPlan());
        if (null !== $code && null !== $plans->findOneByCode($code)) {
            return $this->redirectToRoute('app_flight_plan_show', ['code' => $code]);
        }

        return $this->render('flight/session.html.twig', ['flight' => $flight, 'session' => FlightSession::of($flights->findSession($flight))]);
    }

    /** Replay data of the flights on the flight plan of this one (flights without a library code). */
    #[Route('/{id<\d+>}/session.json', name: 'session_data', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function sessionData(Flight $flight, FlightRepository $flights): JsonResponse
    {
        $response = new JsonResponse(FlightSession::of($flights->findSession($flight))->replayData());
        $response->setPrivate();
        $response->setMaxAge(300);

        return $response;
    }

    /** The file the pilot uploaded (.ftr or .igc), for other analysis software. */
    #[Route('/{id<\d+>}/fichier', name: 'file', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function originalFile(Flight $flight, FlightImporter $importer): BinaryFileResponse
    {
        $path = $importer->pathOf($flight);
        if ('' === $flight->getFileName() || !is_file($path)) {
            throw $this->createNotFoundException('The file of this flight is no longer kept.');
        }
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $flight->getOriginalName(), 'trace.'.pathinfo($flight->getFileName(), \PATHINFO_EXTENSION));
        $response->setPrivate();

        return $response;
    }

    /** The analysed track as an IGC file (one fix per second, task as C records). */
    #[Route('/{id<\d+>}/igc', name: 'igc', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function igc(Flight $flight, IgcExporter $exporter): Response
    {
        $response = new Response($exporter->export($flight), Response::HTTP_OK, ['Content-Type' => 'application/octet-stream']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $exporter->fileName($flight)));
        $response->setPrivate();

        return $response;
    }

    /**
     * What the viewer draws: 1 s series, phases, statistics and task (FlightAnalysis::toArray()),
     * and the netto vario of the glides when the polar of the glider is known.
     */
    #[Route('/{id<\d+>}/analyse.json', name: 'analysis', methods: ['GET'])]
    #[IsGranted(FlightVoter::VIEW, 'flight')]
    public function analysis(Flight $flight): JsonResponse
    {
        $analysis = $flight->getAnalysis();
        $glider = $this->gliders->findOneByName($flight->getGlider());
        if (null !== $glider) {
            $analysis['series']['netto'] = GliderPerformance::of($glider, $analysis)->netto;
        }
        $response = new JsonResponse($analysis);
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    /**
     * Comparison with the flight chosen in the form (`?avec=`), by default the best other flight of
     * the session.
     */
    #[Route('/{id<\d+>}/comparer', name: 'compare_pick', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
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
    #[IsGranted('ROLE_USER')]
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
    #[IsGranted('ROLE_USER')]
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
                'speed' => $analysis['series']['speed'],
                'thermals' => FlightSession::thermalsOf($analysis),
            ];
        }
        $response = new JsonResponse(['task' => $flight->getAnalysis()['task'], 'step' => FlightComparison::STEP, 'flights' => $tracks]);
        $response->setPrivate();
        $response->setMaxAge(300);

        return $response;
    }

    /** The pilot shows the flight to visitors too (home page showcase), or stops. */
    #[Route('/{id<\d+>}/public', name: 'publish', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(FlightVoter::PUBLISH, 'flight')]
    public function publish(Flight $flight, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('publish_flight_'.$flight->getId(), $request->request->getString('_token'))) {
            $flight->setPublic($request->request->getBoolean('public'));
            $entityManager->flush();
            $this->addFlash('info', $flight->isPublic() ? 'Le vol est public : les visiteurs du site peuvent le voir, sans les commentaires.' : 'Le vol n’est plus visible que des membres connectés.');
        }

        return $this->back($request) ?? $this->redirectToRoute('app_flight_show', ['id' => $flight->getId()]);
    }

    #[Route('/{id<\d+>}/supprimer', name: 'delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsGranted(FlightVoter::DELETE, 'flight')]
    public function delete(Flight $flight, Request $request, FlightImporter $importer): Response
    {
        if ($this->isCsrfTokenValid('delete_flight_'.$flight->getId(), $request->request->getString('_token'))) {
            $importer->delete($flight);
            $this->addFlash('success', 'Le vol a été supprimé.');
        }

        return $this->back($request) ?? $this->redirectToRoute('app_flight_index');
    }

    /** Back to the page of the member space the action came from ("Mes vols"), if any. */
    private function back(Request $request): ?Response
    {
        $back = $request->request->getString('retour');

        return str_starts_with($back, '/mon-compte/') ? $this->redirect($back) : null;
    }
}
