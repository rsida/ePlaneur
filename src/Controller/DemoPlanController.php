<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PathItem;
use App\Repository\FlightRepository;
use App\Security\Permission;
use App\Training\PathItemType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Script of an end-to-end demonstration of the site for the administrators (doc/demo-plan.md): every
 * feature in the order of a visit, with what to show, what to say about its teaching purpose and a
 * live link on the club's own flights.
 */
final class DemoPlanController extends AbstractController
{
    #[Route('/plan-de-demo', name: 'app_demo_plan', methods: ['GET'])]
    #[IsGranted(Permission::DemoPlan->value)]
    public function index(FlightRepository $flights, EntityManagerInterface $entityManager): Response
    {
        $items = $entityManager->getRepository(PathItem::class);
        $exercise = null;
        foreach ($items->findBy(['type' => PathItemType::Exercise], ['id' => 'ASC']) as $item) {
            if (null !== $item->getFlightPlan() && null !== $item->getStep()) {
                $exercise = $item;
                break;
            }
        }

        return $this->render('demo_plan/index.html.twig', [
            'showcase' => $flights->findShowcase(),
            'session' => $flights->findRichestSession(),
            'exercise' => $exercise,
            'quiz' => $items->findOneBy(['type' => PathItemType::Quiz], ['id' => 'ASC']),
        ]);
    }
}
