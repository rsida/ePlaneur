<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MembershipSeason;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MembershipSeason>
 */
class MembershipSeasonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MembershipSeason::class);
    }

    /**
     * Seasons open to requests, the earliest first (the current one, then the next during the
     * renewal campaign).
     *
     * @return list<MembershipSeason>
     */
    public function findOpen(): array
    {
        /* @var list<MembershipSeason> */
        return $this->findBy(['open' => true], ['year' => 'ASC']);
    }

    public function findOneByYear(int $year): ?MembershipSeason
    {
        return $this->findOneBy(['year' => $year]);
    }
}
