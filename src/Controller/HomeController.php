<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    /** Posts of the "Ça bouge au club" section: the featured post and the latest ones. */
    private const int NEWS_COUNT = 3;

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(PostRepository $posts): Response
    {
        $featured = $posts->findFeatured();

        return $this->render('home/index.html.twig', [
            'featured' => $featured,
            'latest' => $posts->findLatest(self::NEWS_COUNT - (null !== $featured ? 1 : 0), $featured),
        ]);
    }
}
