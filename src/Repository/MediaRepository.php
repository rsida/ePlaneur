<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, Media> indexed by id
     */
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $indexed = [];
        foreach ($this->findBy(['id' => array_values(array_unique($ids))]) as $media) {
            $indexed[(int) $media->getId()] = $media;
        }

        return $indexed;
    }
}
