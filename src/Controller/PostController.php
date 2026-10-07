<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\News\NewsFilter;
use App\Content\News\NewsLister;
use App\Content\PostPresenter;
use App\Entity\Post;
use App\Security\Permission;
use App\Security\RestrictedContentResponder;
use App\Security\Voter\ContentVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class PostController extends AbstractController
{
    /**
     * News list: the featured post, then the published posts by category and period, 12 per page.
     * Unknown filters give a 404 (MapQueryString validation).
     */
    #[Route('/actualites', name: 'app_post_index', methods: ['GET'])]
    public function index(#[MapQueryString] NewsFilter $filter, NewsLister $lister): Response
    {
        return $this->render('post/index.html.twig', ['news' => $lister->list($filter)]);
    }

    /**
     * Article page. Drafts and scheduled posts are only shown to editors (preview); readers without
     * access get the "Contenu réservé" page.
     */
    #[Route('/actualites/{slug}', name: 'app_post_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Post $post, PostPresenter $presenter, RestrictedContentResponder $restricted): Response
    {
        $preview = !$post->isPublished();
        if ($preview && !$this->isGranted(Permission::PostEdit->value)) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        if (!$this->isGranted(ContentVoter::VIEW, $post)) {
            return $restricted->respond($post);
        }

        return $this->render('post/show.html.twig', [
            'article' => $presenter->present($post),
            'preview' => $preview,
        ]);
    }
}
