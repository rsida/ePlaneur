<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Airspace;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Airspace>
 */
class AirspaceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Airspace::class);
    }

    /** Removes the airspaces of a landscape, before they are read again. */
    public function removeLandscape(string $landscape): int
    {
        return (int) $this->createQueryBuilder('airspace')
            ->delete()
            ->where('airspace.landscape = :landscape')
            ->setParameter('landscape', $landscape)
            ->getQuery()->execute();
    }
}
