<?php

declare(strict_types=1);

namespace App\Controller;

use App\Content\PostPresenter;
use App\Entity\Post;
use App\Security\Permission;
use App\Security\Voter\ContentVoter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PostController extends AbstractController
{
    /**
     * Article page. Drafts and scheduled posts are only shown to editors (preview); restricted posts
     * send visitors to the login page and other users get a 403.
     */
    #[Route('/actualites/{slug}', name: 'app_post_show', requirements: ['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'], methods: ['GET'])]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Post $post, PostPresenter $presenter): Response
    {
        $preview = !$post->isPublished();
        if ($preview && !$this->isGranted(Permission::PostEdit->value)) {
            throw $this->createNotFoundException('Article introuvable.');
        }

        $this->denyAccessUnlessGranted(ContentVoter::VIEW, $post);

        return $this->render('post/show.html.twig', [
            'article' => $presenter->present($post),
            'preview' => $preview,
        ]);
    }
}
