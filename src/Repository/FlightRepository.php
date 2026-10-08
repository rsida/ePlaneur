<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Flight;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Flight>
 */
class FlightRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Flight::class);
    }

    /**
     * @return list<Flight>
     */
    public function findLatest(int $limit, ?User $owner = null): array
    {
        $qb = $this->createQueryBuilder('flight')->orderBy('flight.uploadedAt', 'DESC')->addOrderBy('flight.id', 'DESC')->setMaxResults($limit);
        if (null !== $owner) {
            $qb->andWhere('flight.owner = :owner')->setParameter('owner', $owner);
        }

        /* @var list<Flight> */
        return $qb->getQuery()->getResult();
    }

    /**
     * The flights of the session of a flight: same flight plan (FlightSession).
     *
     * @return list<Flight>
     */
    public function findSession(Flight $flight): array
    {
        if (null === $flight->getFlightPlan()) {
            return [$flight];
        }

        /* @var list<Flight> */
        return $this->findBy(['flightPlan' => $flight->getFlightPlan()], ['id' => 'ASC']);
    }

    /** Number of flights of the session of a flight. */
    public function countSession(Flight $flight): int
    {
        return null === $flight->getFlightPlan() ? 1 : $this->count(['flightPlan' => $flight->getFlightPlan()]);
    }

    /**
     * Every flight of a pilot (uploaded with their account), oldest first.
     *
     * @return list<Flight>
     */
    public function findLogbook(User $owner): array
    {
        $flights = $this->findBy(['owner' => $owner]);
        usort($flights, static fn (Flight $a, Flight $b): int => [$a->getDate(), $a->getId()] <=> [$b->getDate(), $b->getId()]);

        return $flights;
    }

    /**
     * Flights their pilots made public, the latest first (showcase of the home page).
     *
     * @return list<Flight>
     */
    public function findPublic(int $limit): array
    {
        /* @var list<Flight> */
        return $this->findBy(['public' => true], ['uploadedAt' => 'DESC', 'id' => 'DESC'], $limit);
    }
}
