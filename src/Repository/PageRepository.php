<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Page>
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    /**
     * Page reached by a URL path ("le-club/textes-officiels/statuts"), walking down the tree.
     */
    public function findOneByPath(string $path): ?Page
    {
        $page = null;
        foreach (explode('/', trim($path, '/')) as $slug) {
            $page = $this->findOneBy(['parent' => $page, 'slug' => $slug]);
            if (null === $page) {
                return null;
            }
        }

        return $page;
    }
}
