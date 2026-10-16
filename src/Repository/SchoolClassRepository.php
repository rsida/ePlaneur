<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SchoolClass;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SchoolClass>
 */
class SchoolClassRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolClass::class);
    }

    /**
     * The classes of a teacher, the latest first.
     *
     * @return list<SchoolClass>
     */
    public function findOfTeacher(User $teacher): array
    {
        return $this->findBy(['teacher' => $teacher], ['createdAt' => 'DESC', 'id' => 'DESC']);
    }

    /**
     * The classes a pupil joined.
     *
     * @return list<SchoolClass>
     */
    public function findOfPupil(User $pupil): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.pupils', 'p')
            ->andWhere('p = :pupil')->setParameter('pupil', $pupil)
            ->orderBy('c.name')
            ->getQuery()->getResult();
    }

    /** Whether a pupil is in a class of this teacher. */
    public function teaches(User $teacher, User $pupil): bool
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->join('c.pupils', 'p')
            ->andWhere('c.teacher = :teacher')->setParameter('teacher', $teacher)
            ->andWhere('p = :pupil')->setParameter('pupil', $pupil)
            ->getQuery()->getSingleScalarResult() > 0;
    }
}
