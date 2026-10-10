<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Glider;
use App\Repository\GliderRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The gliders of Condor, as their sheets and polars give them (app:gliders:import), open to
 * everyone.
 */
#[Route('/planeurs', name: 'app_glider_')]
final class GliderController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(GliderRepository $gliders): Response
    {
        return $this->render('glider/index.html.twig', ['gliders' => $gliders->findAllWithFlightCounts()]);
    }

    #[Route('/{name}', name: 'show', requirements: ['name' => '[A-Za-z0-9_.-]+'], methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['name' => 'name'])] Glider $glider): Response
    {
        $polar = $glider->getPolar();
        $from = $glider->getMinSpeed() ?? 0.0;
        $empty = $glider->getReferenceMass();
        $full = $glider->getMaxWingLoading() * $glider->getWingArea();
        $curves = [['label' => \sprintf('Sans eau, %s kg/m²', number_format($glider->getMinWingLoading(), 1, ',', ' ')), 'points' => $polar->curve(1.0, $from, $glider->getMaxSpeed())]];
        if ($full > $empty + 1) {
            $curves[] = ['label' => \sprintf('Pleine charge, %s kg/m²', number_format($glider->getMaxWingLoading(), 1, ',', ' ')), 'points' => $polar->curve($full / $empty, $from, $glider->getMaxSpeed())];
        }

        return $this->render('glider/show.html.twig', [
            'glider' => $glider,
            'curves' => $curves,
            'empty' => ['minimumSink' => $polar->minimumSink(), 'bestGlide' => $polar->bestGlide()],
            'full' => $full > $empty + 1 ? ['minimumSink' => $polar->minimumSink($full / $empty), 'bestGlide' => $polar->bestGlide($full / $empty)] : null,
            // MacCready speeds for a few climb rates, without water
            'speedsToFly' => array_map(static fn (float $climb): array => ['climb' => $climb] + $polar->speedToFly($climb), [0.5, 1.0, 1.5, 2.0, 3.0, 4.0]),
        ]);
    }
}
