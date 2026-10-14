<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The site installed as an application (roadmap 11l, doc/installable-site.md): its manifest, its
 * service worker (served from the root to control every page) and the page shown offline.
 */
final class AppController extends AbstractController
{
    /** Name of the cache of the service worker: raise it when the service worker changes. */
    public const string CACHE = 'eplaneur-v1';

    #[Route('/manifest.webmanifest', name: 'app_manifest', methods: ['GET'])]
    public function manifest(Packages $assets): JsonResponse
    {
        $icon = static fn (string $path, int $size, string $purpose = 'any'): array => ['src' => $assets->getUrl($path), 'sizes' => $size.'x'.$size, 'type' => 'image/png', 'purpose' => $purpose];
        $shortcut = fn (string $name, string $route): array => ['name' => $name, 'url' => $this->generateUrl($route)];
        $response = new JsonResponse([
            'name' => 'Club ePlaneur',
            'short_name' => 'ePlaneur',
            'description' => 'Le club du planeur virtuel : apprendre, pratiquer et partager le vol en planeur sur Condor.',
            'lang' => 'fr',
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            // --palette-cream and --palette-ink of assets/styles/tokens.css: a manifest takes no CSS variable
            'background_color' => '#f7f5ef',
            'theme_color' => '#192630',
            'icons' => [
                $icon('images/brand/icon-192.png', 192),
                $icon('images/brand/logo-512.png', 512),
                $icon('images/brand/icon-maskable-512.png', 512, 'maskable'),
            ],
            'shortcuts' => [
                $shortcut('Mon parcours', 'app_path'),
                $shortcut('Mes vols', 'app_flight_index'),
                $shortcut('Vols en réseau', 'app_session_index'),
                $shortcut('Mon compte', 'app_account'),
            ],
        ]);
        $response->headers->set('Content-Type', 'application/manifest+json');

        return $response;
    }

    #[Route('/sw.js', name: 'app_service_worker', methods: ['GET'])]
    public function serviceWorker(): Response
    {
        $response = $this->render('app/service_worker.js.twig', ['cache' => self::CACHE]);
        $response->headers->set('Content-Type', 'text/javascript; charset=UTF-8');
        // Browsers look for a new service worker at each visit: never from a cache
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    #[Route('/hors-ligne', name: 'app_offline', methods: ['GET'])]
    public function offline(): Response
    {
        return $this->render('app/offline.html.twig');
    }
}
