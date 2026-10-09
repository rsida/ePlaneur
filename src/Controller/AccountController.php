<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Flight;
use App\Entity\User;
use App\Flight\Advice\Advice;
use App\Flight\Advice\FlightAdvisor;
use App\Flight\Logbook;
use App\Repository\FlightCommentRepository;
use App\Repository\FlightRepository;
use App\Repository\MembershipRepository;
use App\Repository\MembershipSeasonRepository;
use App\Security\Permission;
use App\Training\TrainingPath;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Member space (roadmap step 9a, Figma "Dashboard"): dashboard, my flights, my logbook. The
 * profile, the personal data and the membership have their own controllers.
 */
#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    private const int FLIGHTS_PER_PAGE = 10;

    #[Route('/mon-compte', name: 'app_account', methods: ['GET'])]
    public function index(FlightRepository $flights, FlightCommentRepository $comments, MembershipRepository $memberships, MembershipSeasonRepository $seasons, Logbook $logbook, FlightAdvisor $advisor): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $book = $logbook->of($user);
        $recent = array_reverse(\array_slice($book['flights'], -3));
        // Speed of the latest finished tasks, oldest first, for the small chart
        $tasks = array_values(array_filter($book['points'], static fn (array $point): bool => null !== $point['taskSpeed']));
        $earned = array_values(array_filter($book['badges'], static fn (array $item): bool => null !== $item['flight']));
        usort($earned, static fn (array $a, array $b): int => $b['flight']->getDate() <=> $a['flight']->getDate());
        $latestTip = null;
        foreach (array_reverse($book['flights']) as $flight) {
            foreach ($advisor->advise($flight->getAnalysis()) as $advice) {
                if (Advice::TIP === $advice->kind) {
                    $latestTip = ['advice' => $advice, 'flight' => $flight];
                    break 2;
                }
            }
        }

        return $this->render('account/index.html.twig', [
            'section' => 'dashboard',
            'membership' => $memberships->findByUser($user)[0] ?? null,
            'open' => $seasons->findOpen(),
            'recent' => $recent,
            'count' => \count($book['flights']),
            'speeds' => \array_slice($tasks, -8),
            'badges' => \array_slice($earned, 0, 3),
            'tip' => $latestTip,
            'comments' => $comments->findOnFlightsOf($user, 3),
            'to_comment' => $this->isGranted(Permission::FlightReview->value) ? $flights->findToComment($user, 5) : [],
        ]);
    }

    /** The account's flights: filters, sort, pages; each one can be opened, made public or deleted. */
    #[Route('/mon-compte/vols', name: 'app_account_flights', methods: ['GET'])]
    public function flights(Request $request, FlightRepository $flights, ClockInterface $clock): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);
        $season = $request->query->getString('saison', (string) $clock->now()->format('Y'));
        $filters = [
            'season' => ctype_digit($season) ? (int) $season : null,
            'landscape' => $request->query->getString('paysage') ?: null,
            'plan' => $request->query->getString('plan') ?: null,
            'finished' => match ($request->query->getString('circuit')) {
                'termine' => true, 'non-termine' => false, default => null,
            },
            'public' => match ($request->query->getString('visibilite')) {
                'public' => true, 'prive' => false, default => null,
            },
            'sort' => $request->query->getString('tri', 'recent'),
        ];
        $page = max(1, $request->query->getInt('page', 1));
        $result = $flights->findForOwner($user, $filters, $page, self::FLIGHTS_PER_PAGE);
        $season = $flights->findForOwner($user, ['season' => $filters['season']], 1, \PHP_INT_MAX)['flights'];

        return $this->render('account/flights.html.twig', [
            'section' => 'flights',
            'flights' => $result['flights'],
            'total' => $result['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($result['total'] / self::FLIGHTS_PER_PAGE)),
            'filters' => $request->query->all(),
            'season' => $filters['season'],
            'choices' => $flights->filterChoices($user),
            'years' => range((int) $clock->now()->format('Y'), 2024),
            'season_count' => \count($season),
            'season_hours' => array_sum(array_map(static fn (Flight $flight): int => $flight->getDuration(), $season)),
            'season_distance' => array_sum(array_map(static fn (Flight $flight): int => $flight->getDistance(), $season)),
        ]);
    }

    #[Route('/mon-compte/carnet', name: 'app_account_logbook', methods: ['GET'])]
    public function logbook(Logbook $logbook, TrainingPath $training): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $this->render('account/logbook.html.twig', ['section' => 'logbook', 'pilot' => $user, 'own' => true, 'path' => $training->of($user), 'training_forms' => []] + $logbook->of($user));
    }
}
