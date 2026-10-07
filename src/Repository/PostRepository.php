<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\User;
use App\Security\Visibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * The latest published post marked "À la une" that the reader may see in lists.
     */
    public function findFeatured(?User $reader): ?Post
    {
        /* @var Post|null */
        return $this->published($reader)
            ->andWhere('p.featured = true')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Latest published posts the reader may see in lists (reserved ones may show a padlock).
     *
     * @return list<Post>
     */
    public function findLatest(?User $reader, int $limit, ?Post $exclude = null): array
    {
        $qb = $this->published($reader)->setMaxResults($limit);
        if (null !== $exclude) {
            $qb->andWhere('p != :exclude')->setParameter('exclude', $exclude);
        }

        /* @var list<Post> */
        return $qb->getQuery()->getResult();
    }

    /**
     * One page of the news list: published posts of a category and a period (metropolitan France
     * time), newest first.
     *
     * @return Paginator<Post>
     */
    public function paginateNews(?User $reader, ?Category $category, ?int $year, ?int $month, int $page, int $perPage, ?Post $exclude = null): Paginator
    {
        $qb = $this->published($reader);
        if (null !== $category) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category);
        }
        if (null !== $year) {
            $paris = new \DateTimeZone('Europe/Paris');
            $start = new \DateTimeImmutable(\sprintf('%d-%02d-01 00:00', $year, $month ?? 1), $paris);
            $end = $start->modify(null !== $month ? '+1 month' : '+1 year');
            $qb->andWhere('p.publishedAt >= :start AND p.publishedAt < :end')
                ->setParameter('start', $start->setTimezone(new \DateTimeZone(date_default_timezone_get())))
                ->setParameter('end', $end->setTimezone(new \DateTimeZone(date_default_timezone_get())));
        }
        if (null !== $exclude) {
            $qb->andWhere('p != :exclude')->setParameter('exclude', $exclude);
        }
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);

        return new Paginator($qb, fetchJoinCollection: false);
    }

    /**
     * Number of published posts the reader may see in lists, per category id (key 0: without category).
     *
     * @return array<int, int>
     */
    public function countPublishedByCategory(?User $reader): array
    {
        $rows = $this->published($reader)
            ->select('IDENTITY(p.category) AS categoryId', 'COUNT(p.id) AS total')
            ->resetDQLPart('orderBy')
            ->groupBy('p.category')
            ->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['categoryId']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Publication dates of the published posts the reader may see in lists, to offer the years and
     * months of the list filters.
     *
     * @return list<\DateTimeImmutable>
     */
    public function publishedDates(?User $reader): array
    {
        $rows = $this->published($reader)
            ->select('p.publishedAt')
            ->getQuery()->getArrayResult();

        return array_map(static fn (array $row): \DateTimeImmutable => $row['publishedAt'], $rows);
    }

    /**
     * Published posts the reader may see in lists, newest first, with their cover and category: the
     * ones they may open, and the reserved ones announced to the others (shown with a padlock); the
     * same rule as ContentVoter::LIST (administrators see everything).
     */
    private function published(?User $reader): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('cover', 'category')
            ->leftJoin('p.cover', 'cover')
            ->leftJoin('p.category', 'category')
            ->andWhere('p.publishedAt IS NOT NULL AND p.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC');

        if ($reader?->hasAllPermissions()) {
            return $qb;
        }
        $listed = $qb->expr()->orX('p.visibility = :public', 'p.announced = true');
        $qb->setParameter('public', Visibility::Public->value);
        if (null !== $reader) {
            $listed->add('p.visibility = :authenticated');
            $listed->add('EXISTS (SELECT 1 FROM '.Post::class.' allowed JOIN allowed.allowedGroups allowedGroup WHERE allowed = p AND allowedGroup IN (:groups))');
            $qb->setParameter('authenticated', Visibility::Authenticated->value)
                ->setParameter('groups', $reader->getEffectiveGroups() ?: [0]);
        }

        return $qb->andWhere($listed);
    }
}
