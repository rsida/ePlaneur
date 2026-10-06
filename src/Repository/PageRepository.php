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

    /**
     * Every page in tree order (each page followed by its sub-pages), with its depth (0 = root).
     *
     * @return list<array{page: Page, depth: int}>
     */
    public function findTree(): array
    {
        /** @var list<Page> $pages */
        $pages = $this->createQueryBuilder('page')->orderBy('page.position', 'ASC')->addOrderBy('page.title', 'ASC')->getQuery()->getResult();
        $byParent = [];
        foreach ($pages as $page) {
            $byParent[$page->getParent()?->getId() ?? 0][] = $page;
        }

        $tree = [];
        $walk = static function (int $parentId, int $depth) use (&$walk, &$tree, $byParent): void {
            foreach ($byParent[$parentId] ?? [] as $page) {
                $tree[] = ['page' => $page, 'depth' => $depth];
                $walk((int) $page->getId(), $depth + 1);
            }
        };
        $walk(0, 0);

        return $tree;
    }
}
