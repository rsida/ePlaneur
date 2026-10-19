<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\RouterInterface;

/**
 * Every page of the site can be reached by following links: a menu, another page, an e-mail, or the
 * redirect that ends a form. A page whose route name appears nowhere but in its own #[Route] is an
 * interface nobody finds (decision of 2026-10-19).
 */
final class EveryPageIsLinkedTest extends KernelTestCase
{
    /** Pages reached otherwise, with the reason. */
    private const array REACHED_OTHERWISE = [
        'app_site_tour' => 'item "Le nouveau site" of the main menu, for the committee (data, NavigationFixtures)',
        'app_toolkit' => 'style guide of the developers, dev environment only',
    ];

    public function testEveryPageHasALinkLeadingToIt(): void
    {
        $sources = '';
        foreach ((new Finder())->files()->in([\dirname(__DIR__, 2).'/templates', \dirname(__DIR__, 2).'/src', \dirname(__DIR__, 2).'/assets'])->name(['*.twig', '*.php', '*.js']) as $file) {
            // The definitions of the routes do not lead anywhere
            $sources .= preg_replace('/#\[Route\(.*$/m', '', $file->getContents());
        }

        $orphans = [];
        foreach (self::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            $methods = $route->getMethods();
            if (str_starts_with($name, '_') || str_starts_with($name, 'admin') || ([] !== $methods && !\in_array('GET', $methods, true))
                // Data, files and machine-readable addresses are fetched by the pages, not visited
                || 1 === preg_match('/\.(json|ics|txt|js|xml|webmanifest|csv)$|\/(igc|fichier)$/', $route->getPath())
                || isset(self::REACHED_OTHERWISE[$name])) {
                continue;
            }
            if (1 !== preg_match('/[\'"]'.preg_quote($name, '/').'[\'"]/', $sources)) {
                $orphans[] = $name.' '.$route->getPath();
            }
        }

        self::assertSame([], $orphans, 'Pages without any link leading to them');
    }
}
