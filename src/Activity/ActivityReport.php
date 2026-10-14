<?php

declare(strict_types=1);

namespace App\Activity;

use App\Entity\Flight;
use App\Entity\FlightComment;
use App\Entity\Membership;
use App\Entity\NetworkSession;
use App\Entity\PathCompletion;
use App\Entity\StepProgress;
use App\Entity\User;
use App\Training\ProgressStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Activity of the club month by month (roadmap 11i, doc/activity.md), for the committee and the
 * general meeting: accounts, memberships, flights and flight hours, active pilots, network sessions
 * and the tracks uploaded for them, training (steps validated, items of "Mon parcours", trainers' comments). Read from the dates
 * already stored, nothing new is recorded.
 */
final readonly class ActivityReport
{
    /** Months shown when no year is asked: the last twelve, the current one included. */
    private const int MONTHS = 12;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The months of a calendar year (up to the current month), or the last twelve.
     *
     * @return array{months: list<ActivityMonth>, total: ActivityMonth, from: \DateTimeImmutable, to: \DateTimeImmutable}
     */
    public function of(?int $year = null): array
    {
        $now = $this->clock->now();
        $current = $now->modify('first day of this month')->setTime(0, 0);
        $from = null === $year ? $current->modify(\sprintf('-%d months', self::MONTHS - 1)) : new \DateTimeImmutable(\sprintf('%d-01-01', $year), $now->getTimezone());
        $to = null === $year ? $current : min($current, new \DateTimeImmutable(\sprintf('%d-12-01', $year), $now->getTimezone()));

        /** @var array<string, array<string, mixed>> $counts */
        $counts = [];
        for ($month = $from; $month <= $to; $month = $month->modify('+1 month')) {
            $counts[$month->format('Y-m')] = ['month' => $month, 'pilots' => []];
        }
        $add = static function (?\DateTimeInterface $at, string $what, int|float $value = 1) use (&$counts): void {
            $key = $at?->format('Y-m');
            if (null !== $key && isset($counts[$key])) {
                $counts[$key][$what] = ($counts[$key][$what] ?? 0) + $value;
            }
        };

        foreach ($this->dates(User::class, 'createdAt') as $at) {
            $add($at, 'accounts');
        }
        foreach ($this->dates(Membership::class, 'validatedAt') as $at) {
            $add($at, 'memberships');
        }
        foreach ($this->dates(NetworkSession::class, 'date') as $at) {
            $add($at, 'sessions');
        }
        foreach ($this->dates(StepProgress::class, 'updatedAt', ['status' => ProgressStatus::Validated]) as $at) {
            $add($at, 'steps');
        }
        foreach ($this->dates(PathCompletion::class, 'doneAt') as $at) {
            $add($at, 'items');
        }
        foreach ($this->dates(FlightComment::class, 'createdAt') as $at) {
            $add($at, 'comments');
        }

        $pilots = [];
        foreach ($this->flights() as $flight) {
            $at = $flight['flownOn'] ?? $flight['sessionDate'] ?? $flight['uploadedAt'];
            $key = $at->format('Y-m');
            if (!isset($counts[$key])) {
                continue;
            }
            $add($at, 'flights');
            $add($at, 'seconds', $flight['duration']);
            if (null !== $flight['session']) {
                $add($at, 'participations');
            }
            if (null !== $flight['owner']) {
                $counts[$key]['pilots'][$flight['owner']] = true;
                $pilots[$flight['owner']] = true;
            }
        }

        $months = array_values(array_map(static fn (array $count): ActivityMonth => ActivityMonth::of($count['month'], $count, \count($count['pilots'])), $counts));

        return [
            'months' => $months,
            'total' => ActivityMonth::sum($months, \count($pilots)),
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Years with activity, the latest first: the choices of the report.
     *
     * @return list<int>
     */
    public function years(): array
    {
        $first = $this->entityManager->createQuery('SELECT MIN(u.createdAt) FROM '.User::class.' u')->getSingleScalarResult();
        $start = null !== $first ? (int) substr((string) $first, 0, 4) : (int) $this->clock->now()->format('Y');

        return range((int) $this->clock->now()->format('Y'), min($start, (int) $this->clock->now()->format('Y')));
    }

    /**
     * One date field of every row of an entity.
     *
     * @param class-string         $entity
     * @param array<string, mixed> $where
     *
     * @return list<?\DateTimeInterface>
     */
    private function dates(string $entity, string $field, array $where = []): array
    {
        $query = $this->entityManager->createQueryBuilder()->select('e.'.$field.' AS at')->from($entity, 'e');
        foreach ($where as $name => $value) {
            $query->andWhere(\sprintf('e.%s = :%s', $name, $name))->setParameter($name, $value);
        }

        return array_column($query->getQuery()->getArrayResult(), 'at');
    }

    /**
     * What the report reads of the flights, without loading their analysis.
     *
     * @return list<array{flownOn: ?\DateTimeImmutable, uploadedAt: \DateTimeImmutable, sessionDate: ?\DateTimeImmutable, duration: int, owner: ?int, session: ?int}>
     */
    private function flights(): array
    {
        /* @var list<array{flownOn: ?\DateTimeImmutable, uploadedAt: \DateTimeImmutable, sessionDate: ?\DateTimeImmutable, duration: int, owner: ?int, session: ?int}> */
        return $this->entityManager->createQueryBuilder()
            ->select('f.flownOn', 'f.uploadedAt', 's.date AS sessionDate', 'f.duration', 'IDENTITY(f.owner) AS owner', 'IDENTITY(f.session) AS session')
            ->from(Flight::class, 'f')
            ->leftJoin('f.session', 's')
            ->getQuery()
            ->getArrayResult();
    }
}
