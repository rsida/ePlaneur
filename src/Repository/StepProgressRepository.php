<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StepProgress;
use App\Entity\User;
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
}
