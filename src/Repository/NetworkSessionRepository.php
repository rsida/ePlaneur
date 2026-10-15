<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NetworkSession>
 */
class NetworkSessionRepository extends ServiceEntityRepository
{
    /** Categories of the filters, by the letter of the flight plan code. */
    public const array CATEGORIES = ['initiation' => 'd', 'standard' => 's', 'competition' => 'c'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NetworkSession::class);
    }

    /**
     * Sessions from today on, the nearest first.
     *
     * @return list<NetworkSession>
     */
    public function findUpcoming(\DateTimeImmutable $now, int $limit): array
    {
        /* @var list<NetworkSession> */
        return $this->withPlan()
            ->where('s.date >= :today')
            ->setParameter('today', $now->setTime(0, 0))
            ->orderBy('s.date', 'ASC')
            ->addOrderBy('s.startTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions of a day whose reminder was not e-mailed yet.
     *
     * @return list<NetworkSession>
     */
    public function findToRemind(\DateTimeImmutable $day): array
    {
        /* @var list<NetworkSession> */
        return $this->withPlan()
            ->where('s.date = :day')->setParameter('day', $day->setTime(0, 0))
            ->andWhere('s.reminderSentAt IS NULL')
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions of the last days before a day whose results were not e-mailed yet.
     *
     * @return list<NetworkSession>
     */
    public function findResultsToSend(\DateTimeImmutable $before, int $days): array
    {
        $day = $before->setTime(0, 0);

        /* @var list<NetworkSession> */
        return $this->withPlan()
            ->where('s.date < :day AND s.date >= :from')
            ->setParameter('day', $day)
            ->setParameter('from', $day->modify(\sprintf('-%d days', $days)))
            ->andWhere('s.resultsSentAt IS NULL')
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions from a day on, the nearest first: the calendar of the sessions.
     *
     * @return list<NetworkSession>
     */
    public function findFrom(\DateTimeImmutable $day, int $limit): array
    {
        /* @var list<NetworkSession> */
        return $this->withPlan()
            ->where('s.date >= :day')
            ->setParameter('day', $day->setTime(0, 0))
            ->orderBy('s.date', 'ASC')
            ->addOrderBy('s.startTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Past sessions, the latest first, filtered by year (`annee`), Condor version (`condor`: 2 or 3),
     * category (`categorie`, a key of CATEGORIES), dedicated or open (`type`: `ouverte` or a label).
     *
     * @param array<string, string> $filters
     *
     * @return array{sessions: list<NetworkSession>, total: int}
     */
    public function findPast(\DateTimeImmutable $now, array $filters, int $page, int $perPage): array
    {
        $query = $this->withPlan()->where('s.date < :today')->setParameter('today', $now->setTime(0, 0));
        if (ctype_digit($filters['annee'] ?? '')) {
            $year = (int) $filters['annee'];
            $query->andWhere('s.date >= :from AND s.date < :to')
                ->setParameter('from', new \DateTimeImmutable($year.'-01-01'))
                ->setParameter('to', new \DateTimeImmutable(($year + 1).'-01-01'));
        }
        if (\in_array($filters['condor'] ?? '', ['2', '3'], true)) {
            $query->andWhere('p.code LIKE :version')->setParameter('version', 'C'.$filters['condor'].'-%');
        }
        if (isset(self::CATEGORIES[$filters['categorie'] ?? ''])) {
            $query->andWhere('p.code LIKE :category')->setParameter('category', 'C_-'.self::CATEGORIES[$filters['categorie']].'%');
        }
        $type = $filters['type'] ?? '';
        if ('ouverte' === $type) {
            $query->andWhere('s.dedicatedTo IS NULL');
        } elseif ('' !== $type) {
            $query->andWhere('s.dedicatedTo = :dedicated')->setParameter('dedicated', $type);
        }
        $total = (int) (clone $query)->select('COUNT(s.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        /** @var list<NetworkSession> $sessions */
        $sessions = $query->orderBy('s.date', 'DESC')->addOrderBy('s.startTime', 'DESC')->addOrderBy('s.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['sessions' => $sessions, 'total' => $total];
    }

    /**
     * Sessions on a flight plan, the latest first.
     *
     * @return list<NetworkSession>
     */
    public function findByFlightPlan(FlightPlan $plan): array
    {
        /* @var list<NetworkSession> */
        return $this->findBy(['flightPlan' => $plan], ['date' => 'DESC', 'id' => 'DESC']);
    }

    public function findOneByRaceId(int $raceId): ?NetworkSession
    {
        return $this->findOneBy(['raceId' => $raceId]);
    }

    /**
     * A session on this flight plan without results yet, held on the day of the flight or up to
     * three days before (a .ftr file has no date: its upload date is used).
     */
    public function findOpenFor(string $code, \DateTimeImmutable $day): ?NetworkSession
    {
        /* @var ?NetworkSession */
        return $this->withPlan()
            ->where('p.code = :code AND s.raceId IS NULL AND s.date BETWEEN :from AND :to')
            ->setParameter('code', $code)
            ->setParameter('from', $day->setTime(0, 0)->modify('-3 days'))
            ->setParameter('to', $day->setTime(0, 0))
            ->orderBy('s.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<int> years with past sessions, latest first */
    public function years(): array
    {
        $dates = $this->createQueryBuilder('s')->select('MIN(s.date) AS first, MAX(s.date) AS last')->getQuery()->getSingleResult();
        if (null === $dates['first']) {
            return [];
        }

        return range((int) substr((string) $dates['last'], 0, 4), (int) substr((string) $dates['first'], 0, 4));
    }

    /** @return list<string> labels of the dedicated sessions */
    public function dedicatedLabels(): array
    {
        return array_column($this->createQueryBuilder('s')
            ->select('DISTINCT s.dedicatedTo AS label')
            ->where('s.dedicatedTo IS NOT NULL')
            ->orderBy('label', 'ASC')
            ->getQuery()
            ->getScalarResult(), 'label');
    }

    private function withPlan(): QueryBuilder
    {
        return $this->createQueryBuilder('s')->addSelect('p')->join('s.flightPlan', 'p');
    }
}
