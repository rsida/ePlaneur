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
     * The other flights in the same air as a flight: those of its network session, or else of the
     * same Condor race (race id of the .ftr). Not the same flight plan: flown another day, the
     * weather and its thermals differ.
     *
     * @return list<Flight>
     */
    public function findSameAir(Flight $flight): array
    {
        $session = $flight->getSession();
        $flights = match (true) {
            null !== $session => array_values($session->getFlights()->toArray()),
            null !== $flight->getRaceId() => $this->findBy(['raceId' => $flight->getRaceId()], ['id' => 'ASC']),
            default => [],
        };

        return array_values(array_filter($flights, static fn (Flight $other): bool => $other->getId() !== $flight->getId()));
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

    /**
     * Flights on a flight plan of the library: their Condor task name is its code, or starts with
     * it followed by "_" or a space ("C3-c060_Laragne...", not "C3-c060a_...").
     *
     * @return list<Flight>
     */
    public function findOnFlightPlan(string $code): array
    {
        $escaped = addcslashes($code, '%_\\');

        /* @var list<Flight> */
        return $this->createQueryBuilder('f')
            ->where('f.flightPlan = :code OR f.flightPlan LIKE :underscore OR f.flightPlan LIKE :space')
            ->setParameter('code', $code)
            ->setParameter('underscore', $escaped.'\\_%')
            ->setParameter('space', $escaped.' %')
            ->orderBy('f.id', 'ASC')
            ->getQuery()
            ->getResult();
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

    /**
     * Flights of others without any comment yet, the latest first (trainers' dashboard).
     *
     * @return list<Flight>
     */
    public function findToComment(User $trainer, int $limit): array
    {
        /* @var list<Flight> */
        return $this->createQueryBuilder('flight')
            ->leftJoin('flight.comments', 'comment')
            ->andWhere('flight.owner IS NOT NULL AND flight.owner != :trainer')->setParameter('trainer', $trainer)
            ->groupBy('flight.id')
            ->having('COUNT(comment.id) = 0')
            ->orderBy('flight.uploadedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /**
     * Flights of an account for "Mes vols": filtered and sorted, one page.
     *
     * @param array{season?: ?int, landscape?: ?string, plan?: ?string, finished?: ?bool, public?: ?bool, sort?: string} $filters
     *
     * @return array{flights: list<Flight>, total: int}
     */
    public function findForOwner(User $owner, array $filters, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('flight')->andWhere('flight.owner = :owner')->setParameter('owner', $owner);
        if (null !== ($filters['season'] ?? null)) {
            $qb->andWhere('COALESCE(flight.flownOn, flight.uploadedAt) >= :from AND COALESCE(flight.flownOn, flight.uploadedAt) < :to')
                ->setParameter('from', new \DateTimeImmutable($filters['season'].'-01-01'))
                ->setParameter('to', new \DateTimeImmutable(($filters['season'] + 1).'-01-01'));
        }
        if (null !== ($filters['landscape'] ?? null)) {
            $qb->andWhere('flight.landscape = :landscape')->setParameter('landscape', $filters['landscape']);
        }
        if (null !== ($filters['plan'] ?? null)) {
            $qb->andWhere('flight.flightPlan = :plan')->setParameter('plan', $filters['plan']);
        }
        if (null !== ($filters['finished'] ?? null)) {
            $qb->andWhere($filters['finished'] ? 'flight.taskSpeed IS NOT NULL' : 'flight.taskSpeed IS NULL');
        }
        if (null !== ($filters['public'] ?? null)) {
            $qb->andWhere('flight.public = :public')->setParameter('public', $filters['public']);
        }
        match ($filters['sort'] ?? 'recent') {
            'ancien' => $qb->orderBy('flight.uploadedAt', 'ASC'),
            'vitesse' => $qb->orderBy('flight.taskSpeed', 'DESC'),
            'distance' => $qb->orderBy('flight.distance', 'DESC'),
            default => $qb->orderBy('flight.uploadedAt', 'DESC'),
        };
        $qb->addOrderBy('flight.id', 'DESC');
        $total = (int) (clone $qb)->select('COUNT(flight.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        /** @var list<Flight> $flights */
        $flights = $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getResult();

        return ['flights' => $flights, 'total' => $total];
    }

    /**
     * Landscapes and flight plans of an account's flights, for the filters.
     *
     * @return array{landscapes: list<string>, plans: list<string>}
     */
    public function filterChoices(User $owner): array
    {
        $rows = $this->createQueryBuilder('flight')->select('DISTINCT flight.landscape AS landscape, flight.flightPlan AS plan')
            ->andWhere('flight.owner = :owner')->setParameter('owner', $owner)->getQuery()->getArrayResult();
        $landscapes = array_values(array_unique(array_filter(array_column($rows, 'landscape'))));
        $plans = array_values(array_unique(array_filter(array_column($rows, 'plan'))));
        sort($landscapes);
        sort($plans);

        return ['landscapes' => $landscapes, 'plans' => $plans];
    }
}
