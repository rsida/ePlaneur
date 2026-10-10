<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Glider;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Glider>
 */
class GliderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Glider::class);
    }

    public function findOneByName(?string $name): ?Glider
    {
        return null !== $name && '' !== $name ? $this->findOneBy(['name' => $name]) : null;
    }

    /**
     * Gliders by name, with the number of tracks flown on each.
     *
     * @return list<array{glider: Glider, flights: int}>
     */
    public function findAllWithFlightCounts(): array
    {
        /** @var list<array{0: Glider, flights: int|string}> $rows */
        $rows = $this->createQueryBuilder('g')
            ->select('g', 'COUNT(f.id) AS flights')
            ->leftJoin(\App\Entity\Flight::class, 'f', 'WITH', 'f.glider = g.name')
            ->groupBy('g.id')
            ->orderBy('g.name')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $row): array => ['glider' => $row[0], 'flights' => (int) $row['flights']], $rows);
    }
}
