<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StepProgress;
use App\Entity\User;
use App\Training\ProgressStatus;
use App\Training\TrainingStep;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StepProgress>
 */
class StepProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StepProgress::class);
    }

    /**
     * Progress of a pilot by step number.
     *
     * @return array<int, StepProgress>
     */
    public function findByPilot(User $pilot): array
    {
        $byStep = [];
        foreach ($this->findBy(['pilot' => $pilot]) as $progress) {
            $byStep[$progress->getStep()->value] = $progress;
        }

        return $byStep;
    }

    /**
     * Ids of the pilots whose trainers validated a step.
     *
     * @return list<int>
     */
    public function findPilotsWithValidated(TrainingStep $step): array
    {
        return array_map(intval(...), $this->createQueryBuilder('p')
            ->select('IDENTITY(p.pilot)')
            ->andWhere('p.step = :step')->setParameter('step', $step)
            ->andWhere('p.status = :status')->setParameter('status', ProgressStatus::Validated)
            ->getQuery()->getSingleColumnResult());
    }
}
