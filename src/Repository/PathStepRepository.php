<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PathStep;
use App\Training\TrainingStep;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PathStep>
 */
class PathStepRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PathStep::class);
    }

    /**
     * The eight steps in order, with their items and quizzes; a step missing from the database (new
     * database, fixtures) is created.
     *
     * @return list<PathStep>
     */
    public function findPath(): array
    {
        $steps = $this->query();
        if (\count($steps) < \count(TrainingStep::cases())) {
            $known = array_map(static fn (PathStep $step): TrainingStep => $step->getStep(), $steps);
            foreach (TrainingStep::cases() as $step) {
                if (!\in_array($step, $known, true)) {
                    $this->getEntityManager()->persist(new PathStep($step));
                }
            }
            $this->getEntityManager()->flush();
            $steps = $this->query();
        }

        return $steps;
    }

    /**
     * @return list<PathStep>
     */
    private function query(): array
    {
        /* @var list<PathStep> */
        return $this->createQueryBuilder('s')
            ->leftJoin('s.items', 'i')->addSelect('i')
            ->leftJoin('i.quiz', 'q')->addSelect('q')
            ->orderBy('s.step', 'ASC')
            ->addOrderBy('i.position', 'ASC')
            ->addOrderBy('i.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
