<?php

declare(strict_types=1);

namespace App\Controller;

use App\Seo\SearchEngines;
use App\Seo\Sitemap;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * robots.txt, sitemap.xml and llms.txt for search engines and AI crawlers (doc/seo.md).
 */
final class SeoController extends AbstractController
{
    /**
     * Every crawler is welcome, AI ones included, for the widest reach (decision of 2026-10-13);
     * only the account and back-office areas are closed. Other private pages say noindex. A staging
     * copy closes everything.
     */
    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(SearchEngines $searchEngines): Response
    {
        $response = $this->render('seo/robots.txt.twig', ['allowed' => $searchEngines->areAllowed()]);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $response->setPublic()->setMaxAge(3600);
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function sitemap(Sitemap $sitemap, SearchEngines $searchEngines): Response
    {
        if (!$searchEngines->areAllowed()) {
            throw $this->createNotFoundException();
        }
        $response = $this->render('seo/sitemap.xml.twig', ['entries' => $sitemap->entries()]);
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');

        return $response->setPublic()->setMaxAge(3600);
    }

    /**
     * Summary of the site for language models (https://llmstxt.org): the club, then its public
     * pages and latest posts with their lead.
     */
    #[Route('/llms.txt', name: 'app_llms', methods: ['GET'])]
    public function llms(Sitemap $sitemap, SearchEngines $searchEngines): Response
    {
        if (!$searchEngines->areAllowed()) {
            throw $this->createNotFoundException();
        }
        $response = $this->render('seo/llms.txt.twig', ['pages' => $sitemap->pages(), 'posts' => \array_slice($sitemap->posts(), 0, 20)]);
        $response->headers->set('Content-Type', 'text/plain; charset=UTF-8');

        return $response->setPublic()->setMaxAge(3600);
    }
}
