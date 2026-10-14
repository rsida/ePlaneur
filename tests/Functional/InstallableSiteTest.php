<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Controller\AppController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The site installed as an application: manifest, service worker and the page shown offline.
 */
final class InstallableSiteTest extends WebTestCase
{
    public function testTheSiteCanBeInstalled(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');
        self::assertSelectorExists('link[rel="manifest"][href="/manifest.webmanifest"]');
        self::assertSelectorExists('link[rel="apple-touch-icon"]');
        self::assertSelectorExists('[data-controller="install"]');

        $client->request('GET', '/manifest.webmanifest');
        self::assertResponseHeaderSame('Content-Type', 'application/manifest+json');
        $manifest = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame(['Club ePlaneur', 'ePlaneur', '/', 'standalone'], [$manifest['name'], $manifest['short_name'], $manifest['start_url'], $manifest['display']]);
        self::assertContains('maskable', array_column($manifest['icons'], 'purpose'));
        foreach ($manifest['icons'] as $icon) {
            $client->request('GET', $icon['src']);
            self::assertResponseIsSuccessful($icon['src']);
        }
        self::assertContains('/parcours', array_column($manifest['shortcuts'], 'url'));

        $client->request('GET', '/sw.js');
        self::assertResponseHeaderSame('Content-Type', 'text/javascript; charset=UTF-8');
        self::assertStringContainsString('no-cache', (string) $client->getResponse()->headers->get('Cache-Control'));
        $worker = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('const CACHE = "'.AppController::CACHE.'";', $worker);
        self::assertStringContainsString('const OFFLINE = "/hors-ligne";', $worker);

        $client->request('GET', '/hors-ligne');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Vous êtes hors ligne');
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
    }
}
