<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Airfield;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Airfield>
 */
class AirfieldRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Airfield::class);
    }

    /**
     * The landscapes whose airfields were read.
     *
     * @return list<string>
     */
    public function landscapes(): array
    {
        return array_column($this->createQueryBuilder('airfield')
            ->select('DISTINCT airfield.landscape')
            ->orderBy('airfield.landscape')
            ->getQuery()->getScalarResult(), 'landscape');
    }

    /**
     * The airfields of a landscape within a box (degrees).
     *
     * @return list<Airfield>
     */
    public function findInBox(string $landscape, float $south, float $west, float $north, float $east): array
    {
        /* @var list<Airfield> */
        return $this->createQueryBuilder('airfield')
            ->where('airfield.landscape = :landscape')
            ->andWhere('airfield.latitude BETWEEN :south AND :north')
            ->andWhere('airfield.longitude BETWEEN :west AND :east')
            ->setParameter('landscape', $landscape)
            ->setParameter('south', $south)
            ->setParameter('north', $north)
            ->setParameter('west', $west)
            ->setParameter('east', $east)
            ->orderBy('airfield.name')
            ->getQuery()->getResult();
    }
}
