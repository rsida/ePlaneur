<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\FlightPlan;
use App\Flight\FlightClouds;
use App\Flight\FlightSession;
use App\Repository\FlightPlanRepository;
use App\Repository\FlightRepository;
use App\Repository\NetworkSessionRepository;
use App\Security\Permission;
use App\Session\FlightPlanWorkshop;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * A flight plan as the club flew it: its sessions and every track on it (the sheet itself is on
 * eplaneur.fr), the suggestions of the session form, and the workshop that composes the instructions
 * to have a new flight plan written.
 */
#[Route('/plans-de-vol', name: 'app_flight_plan_')]
final class FlightPlanController extends AbstractController
{
    /** Flight plans matching what is typed in the session form. */
    #[Route('/recherche', name: 'search', methods: ['GET'])]
    #[IsGranted(Permission::SessionOrganise->value)]
    public function search(Request $request, FlightPlanRepository $plans): JsonResponse
    {
        return $this->json(array_map(static fn (FlightPlan $plan): array => [
            'code' => $plan->getCode(),
            'label' => $plan->getLabel(),
            'url' => $plan->getSheetUrl(),
        ], $plans->search($request->query->getString('q'), 10)));
    }

    /** Settings of a flight plan to write, turned into instructions for Claude (doc/sessions.md). */
    #[Route('/atelier', name: 'workshop', methods: ['GET'])]
    #[IsGranted(Permission::SessionOrganise->value)]
    public function workshop(FlightPlanWorkshop $workshop): Response
    {
        return $this->render('flight_plan/workshop.html.twig', [
            'landscapes' => $workshop->landscapes(),
            'gliders' => $workshop->gliders(),
        ]);
    }

    /** Regions, airfields and airspaces of a landscape, for the workshop. */
    #[Route('/atelier/{landscape}.json', name: 'workshop_data', requirements: ['landscape' => '[A-Za-z0-9_-]+'], methods: ['GET'])]
    #[IsGranted(Permission::SessionOrganise->value)]
    public function workshopData(string $landscape, FlightPlanWorkshop $workshop): JsonResponse
    {
        if (!$workshop->offers($landscape)) {
            throw $this->createNotFoundException();
        }
        $response = $this->json($workshop->landscape($landscape));
        $response->setPrivate();
        $response->setMaxAge(3600);

        return $response;
    }

    #[Route('/{code}', name: 'show', requirements: ['code' => 'C[23]-[a-z]\d{3}[a-z]?'], methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['code' => 'code'])] FlightPlan $plan, NetworkSessionRepository $sessions, FlightRepository $flights): Response
    {
        $tracks = $this->isGranted('ROLE_USER') ? $flights->findOnFlightPlan($plan->getCode()) : [];

        return $this->render('flight_plan/show.html.twig', [
            'plan' => $plan,
            'sessions' => $sessions->findByFlightPlan($plan),
            'ranked' => [] !== $tracks ? FlightSession::of($tracks) : null,
        ]);
    }

    /** Replay data of every track on the flight plan, whatever their day ("ghost race"). */
    #[Route('/{code}/traces.json', name: 'data', requirements: ['code' => 'C[23]-[a-z]\d{3}[a-z]?'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function data(#[MapEntity(mapping: ['code' => 'code'])] FlightPlan $plan, FlightRepository $flights, FlightClouds $clouds): JsonResponse
    {
        $tracks = $flights->findOnFlightPlan($plan->getCode());
        $response = new JsonResponse([] !== $tracks ? $clouds->addTo(FlightSession::of($tracks)->replayData(), $tracks) : ['task' => null, 'flights' => []]);
        $response->setPrivate();
        $response->setMaxAge(300);

        return $response;
    }
}
