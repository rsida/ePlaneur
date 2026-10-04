<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Document;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Documents of a category (all when null), grouped by category then in their order.
     * Visibility is not checked here: filter with CONTENT_VIEW.
     *
     * @return list<Document>
     */
    public function findForListing(?int $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->addSelect('file', 'category')
            ->join('d.file', 'file')
            ->leftJoin('d.category', 'category')
            ->orderBy('category.position', 'ASC')
            ->addOrderBy('category.name', 'ASC')
            ->addOrderBy('d.position', 'ASC')
            ->addOrderBy('d.title', 'ASC');

        if (null !== $categoryId) {
            $qb->andWhere('category.id = :category')->setParameter('category', $categoryId);
        }

        /* @var list<Document> */
        return $qb->getQuery()->getResult();
    }
}
