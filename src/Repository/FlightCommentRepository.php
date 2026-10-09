<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FlightComment;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FlightComment>
 */
class FlightCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FlightComment::class);
    }

    /**
     * Latest comments written by others on the flights of an account (member space).
     *
     * @return list<FlightComment>
     */
    public function findOnFlightsOf(User $owner, int $limit): array
    {
        /* @var list<FlightComment> */
        return $this->createQueryBuilder('comment')
            ->join('comment.flight', 'flight')
            ->andWhere('flight.owner = :owner')->setParameter('owner', $owner)
            ->andWhere('comment.author IS NULL OR comment.author != :owner')
            ->orderBy('comment.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }
}
