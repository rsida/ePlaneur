<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    /**
     * The devices of these accounts, or of every account.
     *
     * @param list<User>|null $users
     *
     * @return list<PushSubscription>
     */
    public function findOfUsers(?array $users = null): array
    {
        $query = $this->createQueryBuilder('s')->join('s.user', 'u')->addSelect('u')->orderBy('s.id');
        if (null !== $users) {
            if ([] === $users) {
                return [];
            }
            $query->andWhere('s.user IN (:users)')->setParameter('users', $users);
        }

        return $query->getQuery()->getResult();
    }
}
