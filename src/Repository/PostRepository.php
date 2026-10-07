<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\Post;
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
     * The latest published post marked "À la une", whatever its visibility.
     */
    public function findFeatured(): ?Post
    {
        /* @var Post|null */
        return $this->published()
            ->andWhere('p.featured = true')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /**
     * Latest published posts, whatever their visibility (reserved ones show their access tag).
     *
     * @return list<Post>
     */
    public function findLatest(int $limit, ?Post $exclude = null): array
    {
        $qb = $this->published()->setMaxResults($limit);
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
    public function paginateNews(?Category $category, ?int $year, ?int $month, int $page, int $perPage, ?Post $exclude = null): Paginator
    {
        $qb = $this->published();
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
     * Number of published posts per category id (key 0: without category).
     *
     * @return array<int, int>
     */
    public function countPublishedByCategory(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.category) AS category', 'COUNT(p.id) AS total')
            ->andWhere('p.publishedAt IS NOT NULL AND p.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->groupBy('p.category')
            ->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['category']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Publication dates of the published posts, to offer the years and months of the list filters.
     *
     * @return list<\DateTimeImmutable>
     */
    public function publishedDates(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.publishedAt')
            ->andWhere('p.publishedAt IS NOT NULL AND p.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()->getArrayResult();

        return array_map(static fn (array $row): \DateTimeImmutable => $row['publishedAt'], $rows);
    }

    /** Published posts, newest first, with their cover and category. */
    private function published(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->addSelect('cover', 'category')
            ->leftJoin('p.cover', 'cover')
            ->leftJoin('p.category', 'category')
            ->andWhere('p.publishedAt IS NOT NULL AND p.publishedAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('p.publishedAt', 'DESC')
            ->addOrderBy('p.id', 'DESC');
    }
}
