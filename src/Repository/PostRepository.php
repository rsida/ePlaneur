<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * Latest published posts, most relevant first (same category), excluding the given post.
     * Visibility is not checked here: filter the result with CONTENT_VIEW.
     *
     * @return list<Post>
     */
    public function findRelatedCandidates(Post $post, int $limit = 12): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('cover', 'category')
            ->leftJoin('p.cover', 'cover')
            ->leftJoin('p.category', 'category')
            ->andWhere('p.id != :id')
            ->andWhere('p.publishedAt IS NOT NULL AND p.publishedAt <= :now')
            ->setParameter('id', $post->getId())
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults($limit);

        if (null !== $post->getCategory()) {
            $qb->addSelect('CASE WHEN p.category = :category THEN 0 ELSE 1 END AS HIDDEN relevance')
                ->setParameter('category', $post->getCategory())
                ->orderBy('relevance', 'ASC')
                ->addOrderBy('p.publishedAt', 'DESC');
        } else {
            $qb->orderBy('p.publishedAt', 'DESC');
        }

        /* @var list<Post> */
        return $qb->getQuery()->getResult();
    }
}
