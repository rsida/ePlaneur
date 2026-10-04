<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MenuItem;
use App\Navigation\MenuLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuItem>
 */
class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    /**
     * Every item of a menu with its target page, ordered by position (the tree is built by
     * App\Navigation\MenuBuilder).
     *
     * @return list<MenuItem>
     */
    public function findByLocation(MenuLocation $location): array
    {
        /* @var list<MenuItem> */
        return $this->createQueryBuilder('m')
            ->addSelect('page')
            ->leftJoin('m.page', 'page')
            ->andWhere('m.location = :location')
            ->setParameter('location', $location)
            ->orderBy('m.position', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
