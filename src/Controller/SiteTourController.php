<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\FlightRepository;
use App\Repository\NetworkSessionRepository;
use App\Repository\UserRepository;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Presentation of the site for the committee (doc/site-tour.md): every feature, the most striking
 * ones first, each with a link to see it live on the club's own flights and sessions.
 */
final class SiteTourController extends AbstractController
{
    #[Route('/comite/le-site', name: 'app_site_tour', methods: ['GET'])]
    #[IsGranted(Permission::SiteTour->value)]
    public function index(FlightRepository $flights, NetworkSessionRepository $sessions, UserRepository $users): Response
    {
        $showcase = $flights->findShowcase();

        return $this->render('site_tour/index.html.twig', [
            'showcase' => $showcase,
            'session' => $flights->findRichestSession(),
            'figures' => [
                'flights' => $flights->count([]),
                'hours' => $flights->totalSeconds() / 3600,
                'sessions' => $sessions->count([]),
                'accounts' => $users->count([]),
            ],
        ]);
    }
}
