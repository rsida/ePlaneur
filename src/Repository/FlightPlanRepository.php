<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FlightPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlightPlan>
 */
class FlightPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlightPlan::class);
    }

    public function findOneByCode(string $code): ?FlightPlan
    {
        return $this->findOneBy(['code' => $code]);
    }

    /**
     * Flight plans whose code or route contains the text, codes starting with it first.
     *
     * @return list<FlightPlan>
     */
    public function search(string $text, int $limit): array
    {
        $text = trim($text);
        if ('' === $text) {
            return [];
        }
        $like = '%'.addcslashes($text, '%_\\').'%';

        /* @var list<FlightPlan> */
        return $this->createQueryBuilder('p')
            ->addSelect('CASE WHEN p.code LIKE :prefix THEN 0 ELSE 1 END AS HIDDEN starts')
            ->where('p.code LIKE :like OR p.title LIKE :like')
            ->setParameter('like', $like)
            ->setParameter('prefix', addcslashes($text, '%_\\').'%')
            ->orderBy('starts', 'ASC')
            ->addOrderBy('p.code', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
