<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PathCompletion;
use App\Entity\PathItem;
use App\Entity\User;
use App\Repository\PathCompletionRepository;
use App\Training\ExerciseResult;
use App\Training\ItemView;
use App\Training\PathBadges;
use App\Training\PathItemType;
use App\Training\PilotPath;
use App\Training\RankingLevel;
use App\Training\StepState;
use App\Training\StepView;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * "Mon parcours" (roadmap 11, doc/training-path.md): the training path as a path of progression.
 * Visitors see the steps and their items; a logged-in pilot sees what they did, does the readings,
 * quizzes and exercises of the steps they reached, and compares with the best on each exercise.
 */
#[Route('/parcours', name: 'app_path')]
final class PathController extends AbstractController
{
    /** Pilots listed in the ranking of an exercise. */
    private const int RANKING_SIZE = 10;

    /** `?saison=` value of the ranking of every season. */
    private const string ALL_SEASONS = 'tout';

    public function __construct(
        private readonly PilotPath $path,
    ) {
    }

    #[Route('', name: '', methods: ['GET'])]
    public function index(PathBadges $badges): Response
    {
        $steps = $this->path->of($this->pilot());

        return $this->render('path/index.html.twig', [
            'steps' => $steps,
            'badges' => $badges->of($this->pilot(), $steps),
            'current' => array_find($steps, static fn (StepView $step): bool => StepState::Current === $step->state),
        ]);
    }

    #[Route('/etape/{number<[1-8]>}', name: '_step', methods: ['GET'])]
    public function step(int $number): Response
    {
        $steps = $this->path->of($this->pilot());

        return $this->render('path/step.html.twig', [
            'view' => $steps[$number - 1] ?? throw $this->createNotFoundException(),
            'steps' => $steps,
        ]);
    }

    /** A reading, or an exercise without flight plan, that the pilot says done. */
    #[Route('/element/{id<\d+>}/fait', name: '_done', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function done(PathItem $item, Request $request, PathCompletionRepository $completions, EntityManagerInterface $entityManager, ClockInterface $clock): Response
    {
        [$step] = $this->reach($item);
        if (!$item->isDeclared() || !$this->isCsrfTokenValid('path-done-'.$item->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $pilot = $this->user();
        $completion = $completions->findOneBy(['pilot' => $pilot, 'item' => $item]) ?? new PathCompletion($pilot, $item, $clock->now());
        $entityManager->persist($completion);
        $entityManager->flush();

        return $this->redirectToRoute('app_path_step', ['number' => $step->step->getNumber(), '_fragment' => 'element-'.$item->getId()]);
    }

    /**
     * A quiz: answer every question, then see the result (`?resultat=1`): each answer right or
     * wrong, the right answer and its explanation, and try again. The answers are kept in the
     * session for the result page: a submission redirects there, as Turbo drops a page answered to
     * a form without a redirect.
     */
    #[Route('/quiz/{id<\d+>}', name: '_quiz', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function quiz(PathItem $item, Request $request, PathCompletionRepository $completions, EntityManagerInterface $entityManager, ClockInterface $clock): Response
    {
        [$step, $view] = $this->reach($item);
        $quiz = $item->getQuiz();
        if (PathItemType::Quiz !== $item->getType() || null === $quiz) {
            throw $this->createNotFoundException();
        }
        $session = $request->getSession();
        $key = 'path-quiz-'.$item->getId();

        $answers = $request->query->getBoolean('resultat') ? $session->get($key) : null;
        if (\is_array($answers) && \count($answers) === $quiz->getQuestionCount()) {
            return $this->render('path/quiz.html.twig', [
                'step' => $step,
                'view' => $view,
                'quiz' => $quiz,
                'score' => $quiz->score($answers),
                'answers' => $answers,
            ]);
        }

        $builder = $this->createFormBuilder();
        foreach ($quiz->getQuestions() as $i => $question) {
            $builder->add('q'.$i, ChoiceType::class, [
                'label' => ($i + 1).'. '.$question['question'],
                'choices' => array_flip($question['choices']),
                'expanded' => true,
                'placeholder' => false,
                'constraints' => [new NotNull(message: 'Choisissez une réponse.')],
            ]);
        }
        $form = $builder->getForm()->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $answers = [];
            foreach ($quiz->getQuestions() as $i => $question) {
                $answers[$i] = $form->get('q'.$i)->getData();
            }
            $completion = $completions->findOneBy(['pilot' => $this->user(), 'item' => $item]) ?? new PathCompletion($this->user(), $item, $clock->now());
            $completion->attempt($quiz->score($answers), $clock->now());
            $entityManager->persist($completion);
            $entityManager->flush();
            $session->set($key, $answers);

            return $this->redirectToRoute('app_path_quiz', ['id' => $item->getId(), 'resultat' => 1], Response::HTTP_SEE_OTHER);
        }

        return $this->render('path/quiz.html.twig', [
            'step' => $step,
            'view' => $view,
            'quiz' => $quiz,
            'form' => $form,
            'score' => null,
            'answers' => [],
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * An exercise on a flight plan: the pilot's flights on it checked against its criteria, their
     * personal best and the ranking of the flights that passed it, of a season (`?saison=`, the
     * current one by default, `tout` for every season) and of a level (`?niveau=`, RankingLevel).
     */
    #[Route('/exercice/{id<\d+>}', name: '_exercise', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function exercise(PathItem $item, Request $request, ClockInterface $clock): Response
    {
        [$step, $view] = $this->reach($item, reachable: false);
        if (PathItemType::Exercise !== $item->getType() || null === $item->getFlightPlan()) {
            throw $this->createNotFoundException();
        }
        $current = (int) $clock->now()->format('Y');
        $seasons = array_values(array_unique([$current, ...$this->path->seasons($item)]));
        rsort($seasons);
        $asked = $request->query->getString('saison');
        $season = match (true) {
            self::ALL_SEASONS === $asked => null,
            \in_array((int) $asked, $seasons, true) => (int) $asked,
            default => $current,
        };
        $level = RankingLevel::tryFrom($request->query->getString('niveau')) ?? RankingLevel::All;

        $ranking = $this->path->ranking($item, $season, $level);
        $pilot = $this->user();
        $rank = array_find_key($ranking, static fn (ExerciseResult $result): bool => $result->flight->getOwner() === $pilot);

        return $this->render('path/exercise.html.twig', [
            'step' => $step,
            'view' => $view,
            'ranking' => \count($ranking) >= $item->getRankingFrom() ? \array_slice($ranking, 0, self::RANKING_SIZE) : [],
            'pilots' => \count($ranking),
            'rank' => null !== $rank ? $rank + 1 : null,
            'leader' => $ranking[0]->flight ?? null,
            'season' => $season,
            'seasons' => $seasons,
            'level' => $level,
            'levels' => RankingLevel::cases(),
        ]);
    }

    /**
     * The step of an item and the item as the pilot sees it; the item must be in a step they reached.
     *
     * @return array{StepView, ItemView}
     */
    private function reach(PathItem $item, bool $reachable = true): array
    {
        foreach ($this->path->of($this->user()) as $step) {
            foreach ($step->items as $view) {
                if ($view->item === $item) {
                    if ($reachable && !$step->isReachable()) {
                        throw $this->createAccessDeniedException('Cette étape n’est pas encore débloquée.');
                    }

                    return [$step, $view];
                }
            }
        }
        throw $this->createNotFoundException();
    }

    private function pilot(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function user(): User
    {
        return $this->pilot() ?? throw $this->createAccessDeniedException();
    }
}
