<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\User;
use App\Membership\MembershipStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Membership>
 */
class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    /**
     * Memberships of an account, the latest season first.
     *
     * @return list<Membership>
     */
    public function findByUser(User $user): array
    {
        /* @var list<Membership> */
        return $this->createQueryBuilder('membership')
            ->join('membership.season', 'season')
            ->andWhere('membership.user = :user')->setParameter('user', $user)
            ->orderBy('season.year', 'DESC')->addOrderBy('membership.id', 'DESC')
            ->getQuery()->getResult();
    }

    /** The request of an account for a season that still counts (requested, paid or validated). */
    public function findActive(User $user, MembershipSeason $season): ?Membership
    {
        foreach ($this->findBy(['user' => $user, 'season' => $season], ['id' => 'DESC']) as $membership) {
            if ($membership->getStatus()->isActive()) {
                return $membership;
            }
        }

        return null;
    }

    /** Requests the club has to look at: paid, or requested without online payment. */
    public function countToProcess(): int
    {
        return $this->count(['status' => [MembershipStatus::Requested, MembershipStatus::Paid]]);
    }

    /**
     * Validated memberships of a season.
     *
     * @return list<Membership>
     */
    public function findValidated(MembershipSeason $season): array
    {
        /* @var list<Membership> */
        return $this->findBy(['season' => $season, 'status' => MembershipStatus::Validated]);
    }

    /** Whether the account has a validated membership for a season. */
    public function isMember(User $user, int $year): bool
    {
        return (int) $this->createQueryBuilder('membership')
            ->select('COUNT(membership.id)')
            ->join('membership.season', 'season')
            ->andWhere('membership.user = :user')->setParameter('user', $user)
            ->andWhere('season.year = :year')->setParameter('year', $year)
            ->andWhere('membership.status = :validated')->setParameter('validated', MembershipStatus::Validated)
            ->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Accounts that had a validated membership in a season.
     *
     * @return list<User>
     */
    public function findMembersOf(int $year): array
    {
        $users = [];
        /** @var list<Membership> $memberships */
        $memberships = $this->createQueryBuilder('membership')
            ->join('membership.season', 'season')
            ->andWhere('season.year = :year')->setParameter('year', $year)
            ->andWhere('membership.status = :validated')->setParameter('validated', MembershipStatus::Validated)
            ->getQuery()->getResult();
        foreach ($memberships as $membership) {
            if (null !== $membership->getUser()) {
                $users[(int) $membership->getUser()->getId()] = $membership->getUser();
            }
        }

        return array_values($users);
    }
}
