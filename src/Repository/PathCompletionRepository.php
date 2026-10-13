<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PathCompletion;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PathCompletion>
 */
class PathCompletionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PathCompletion::class);
    }

    /**
     * What a pilot did, by item id.
     *
     * @return array<int, PathCompletion>
     */
    public function findByPilot(User $pilot): array
    {
        $byItem = [];
        foreach ($this->findBy(['pilot' => $pilot]) as $completion) {
            $byItem[(int) $completion->getItem()->getId()] = $completion;
        }

        return $byItem;
    }
}
