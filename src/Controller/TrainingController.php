<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\StepProgress;
use App\Entity\User;
use App\Form\StepProgressType;
use App\Repository\StepProgressRepository;
use App\Security\Voter\FlightVoter;
use App\Training\StepProgressData;
use App\Training\TrainingStep;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Trainers mark the steps of the training path of a pilot from their logbook.
 */
final class TrainingController extends AbstractController
{
    /**
     * The form of each step of a pilot, for their logbook.
     *
     * @return array<int, FormInterface<StepProgressData>>
     */
    public static function forms(User $pilot, StepProgressRepository $progress, FormFactoryInterface $formFactory, callable $action): array
    {
        $byStep = $progress->findByPilot($pilot);
        $forms = [];
        foreach (TrainingStep::cases() as $step) {
            $forms[$step->value] = $formFactory->createNamed('step_'.$step->value, StepProgressType::class, StepProgressData::of($byStep[$step->value] ?? null), [
                'pilot' => $pilot,
                'action' => $action($step),
            ]);
        }

        return $forms;
    }

    #[Route('/vols/carnet/{id<\d+>}/parcours/{step<[1-8]>}', name: 'app_training_step', methods: ['POST'])]
    #[IsGranted(FlightVoter::TRAINING, 'pilot')]
    public function step(#[MapEntity(id: 'id')] User $pilot, int $step, Request $request, StepProgressRepository $progress, EntityManagerInterface $entityManager, ClockInterface $clock): Response
    {
        $trainingStep = TrainingStep::from($step);
        $existing = $progress->findByPilot($pilot)[$step] ?? null;
        $data = StepProgressData::of($existing);
        $form = $this->container->get('form.factory')->createNamed('step_'.$step, StepProgressType::class, $data, ['pilot' => $pilot]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'L’étape n’a pas pu être enregistrée : vérifiez la note (2 000 caractères au plus).');

            return $this->redirectToRoute('app_flight_logbook_pilot', ['id' => $pilot->getId(), '_fragment' => 'parcours']);
        }
        $trainer = $this->getUser();
        \assert($trainer instanceof User);
        if (null === $data->status) {
            if (null !== $existing) {
                $entityManager->remove($existing);
            }
        } else {
            $existing ??= new StepProgress($pilot, $trainingStep, $data->status, $clock->now());
            $existing->mark($data->status, $data->note, $data->flight, $data->validatedOn, $trainer, $clock->now());
            $entityManager->persist($existing);
        }
        $entityManager->flush();
        $this->addFlash('success', \sprintf('%s : enregistrée.', $trainingStep->title()));

        return $this->redirectToRoute('app_flight_logbook_pilot', ['id' => $pilot->getId(), '_fragment' => 'parcours']);
    }
}
