<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\Group;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * The conversations of a user, latest message first: the ones they take part in and the ones
     * written to a group they belong to.
     *
     * @return list<Conversation>
     */
    public function findFor(User $user, ?int $limit = null): array
    {
        /* @var list<Conversation> */
        return $this->visibleTo($user)
            ->orderBy('c.lastMessageAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnread(User $user): int
    {
        return \count(array_filter($this->findFor($user), static fn (Conversation $conversation): bool => $conversation->isUnreadFor($user)));
    }

    private function visibleTo(User $user): QueryBuilder
    {
        $groups = array_map(static fn (Group $group): ?int => $group->getId(), $user->getEffectiveGroups());
        $builder = $this->createQueryBuilder('c')
            ->leftJoin('c.participants', 'p', 'WITH', 'p.user = :user')
            ->setParameter('user', $user);
        if ([] !== $groups) {
            return $builder->where('p.id IS NOT NULL OR c.group IN (:groups)')->setParameter('groups', $groups);
        }

        return $builder->where('p.id IS NOT NULL');
    }
}
