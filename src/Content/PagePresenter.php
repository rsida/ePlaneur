<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Block\ChildPagesBlock;
use App\Entity\Page;
use App\Repository\PageRepository;
use App\Security\Voter\ContentVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Prepares a page: its blocks and table of contents, its published child and sibling pages (reserved
 * ones announced with their access tag; private ones only for their audience).
 */
final readonly class PagePresenter
{
    public function __construct(
        private BlockPlacer $placer,
        private PageRepository $pages,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function present(Page $page): PageView
    {
        $placed = $this->placer->place(['body' => $page->getBody(), 'aside' => $page->getAside()]);
        $siblings = null !== $page->getParent()
            ? $page->getParent()->getChildren()->toArray()
            : $this->pages->findBy(['parent' => null], ['position' => 'ASC', 'title' => 'ASC']);

        $children = $this->visible($page->getChildren()->toArray());
        // Child page cards: the block lists the sub-pages the reader may see
        $withChildren = static fn (PlacedBlock $placed): PlacedBlock => $placed->block instanceof ChildPagesBlock ? $placed->withContext(['pages' => $children]) : $placed;

        $body = array_map($withChildren, $placed['zones']['body']);
        // A page with sub-pages always links to them: cards at the end unless the editor placed them
        if ([] !== $children && !array_any($body, static fn (PlacedBlock $placed): bool => $placed->block instanceof ChildPagesBlock)) {
            $body[] = new PlacedBlock(new ChildPagesBlock(), 'child-pages', null, ['pages' => $children]);
        }

        return new PageView(
            $page,
            $body,
            array_map($withChildren, $placed['zones']['aside']),
            $placed['toc'],
            $children,
            $this->visible($siblings),
        );
    }

    /**
     * Published pages the reader may see listed (ContentVoter::LIST): the ones they may open, and the
     * announced ones with their access tag, so readers know they exist.
     *
     * @param array<Page> $pages
     *
     * @return list<Page>
     */
    private function visible(array $pages): array
    {
        return array_values(array_filter($pages, fn (Page $page): bool => $page->isPublished()
            && $this->authorizationChecker->isGranted(ContentVoter::LIST, $page)));
    }
}
