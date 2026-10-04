<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\PagePresenter;
use App\Repository\PageRepository;
use App\Security\Permission;
use App\Security\RestrictedContentResponder;
use App\Security\Voter\ContentVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    /**
     * Pages of the tree: /le-club, /le-club/textes-officiels/statuts... Lowest priority, so every
     * other route wins (Page::RESERVED_SLUGS keeps root pages away from them).
     */
    #[Route('/{path}', name: 'app_page_show', requirements: ['path' => '[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*){0,3}'], methods: ['GET'], priority: -100)]
    public function show(string $path, PageRepository $pages, PagePresenter $presenter, RestrictedContentResponder $restricted): Response
    {
        $page = $pages->findOneByPath($path);
        $preview = null !== $page && !$page->isPublished();
        if (null === $page || ($preview && !$this->isGranted(Permission::PageManage->value))) {
            throw $this->createNotFoundException('Page introuvable.');
        }

        // A page is only reachable if every ancestor is too
        foreach ($page->getLineage() as $ancestor) {
            if (!$this->isGranted(ContentVoter::VIEW, $ancestor)) {
                return $restricted->respond($page, $ancestor);
            }
        }

        return $this->render('page/show.html.twig', [
            'view' => $presenter->present($page),
            'preview' => $preview,
        ]);
    }
}
