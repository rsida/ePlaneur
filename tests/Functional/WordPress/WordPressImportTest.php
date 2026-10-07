<?php

declare(strict_types=1);

namespace App\Tests\Functional\WordPress;

use App\Entity\Media;
use App\Entity\Post;
use App\Repository\PageRepository;
use App\Security\Visibility;
use App\WordPress\WordPressClient;
use App\WordPress\WordPressImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * app:import-wordpress against a fake WordPress API: pages at their new place, posts, files,
 * reserved content, redirects of the old addresses, and a second import that updates.
 */
final class WordPressImportTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $site = 'https://wp.test';
        $pages = [
            self::item(10, 'le-club', $site.'/le-club/', 'Le Club ePlaneur', '<p>Bienvenue.</p>'),
            self::item(11, 'documents-officiels', $site.'/le-club/documents-officiels/', 'Documents officiels', '<h2>Statuts</h2><p>Voir les <a href="'.$site.'/le-club/documents-officiels/statuts/">statuts</a>.</p>', parent: 10),
            self::item(12, 'login', $site.'/login/', 'Connexion', '<p>Formulaire</p>'),
        ];
        $posts = [
            self::item(20, 'trouver-un-thermique', $site.'/2025/03/11/trouver-un-thermique/', 'Trouver un thermique', '<p>Une méthode.</p><figure class="wp-block-image"><img src="'.$site.'/wp-content/uploads/2025/03/nuage-1024x576.png" alt="Un nuage"></figure>', categories: [12]),
            self::item(21, 'reunion-2025-03-05', $site.'/2025/03/06/reunion-2025-03-05/', 'Réunion du 5 mars', '<p>Décisions.</p>', categories: [1]),
        ];
        $picture = (string) file_get_contents(\dirname(__DIR__, 3).'/fixtures/media/cover-glider-alps.png');

        $http = new MockHttpClient(static function (string $method, string $url, array $options) use ($pages, $posts, $picture): MockResponse {
            $authenticated = isset($options['normalized_headers']['authorization']);
            // The anonymous API hides the content of reserved posts
            $hide = static fn (array $items): array => array_map(static fn (array $item): array => 21 === $item['id'] && !$authenticated ? ['id' => 21, 'title' => ['rendered' => 'Contenu restreint']] + $item : $item, $items);

            return match (true) {
                str_contains($url, '/wp/v2/pages') => new MockResponse(json_encode($pages, \JSON_THROW_ON_ERROR), ['response_headers' => ['x-wp-totalpages' => '1']]),
                str_contains($url, '/wp/v2/posts') => new MockResponse(json_encode($hide($posts), \JSON_THROW_ON_ERROR), ['response_headers' => ['x-wp-totalpages' => '1']]),
                str_contains($url, '/wp/v2/categories') => new MockResponse(json_encode([['id' => 12, 'slug' => 'club'], ['id' => 1, 'slug' => 'uncategorized']], \JSON_THROW_ON_ERROR), ['response_headers' => ['x-wp-totalpages' => '1']]),
                str_ends_with($url, '/nuage.png') => new MockResponse($picture),
                default => new MockResponse('', ['http_code' => 404]),
            };
        });
        static::getContainer()->set(WordPressClient::class, new WordPressClient($http, 'https://wp.test', 'admin', 'app-password'));
    }

    public function testTheSiteIsImportedWithItsRedirects(): void
    {
        $report = static::getContainer()->get(WordPressImporter::class)->import();

        self::assertSame(2, $report->counts()['Pages créées'] ?? 0);
        self::assertSame(2, $report->counts()['Articles créés'] ?? 0);
        $pages = static::getContainer()->get(PageRepository::class);
        $officiels = $pages->findOneByPath('le-club/textes-officiels');
        self::assertNotNull($officiels, 'Pages go to their place in the new tree');
        self::assertSame('Documents officiels', $officiels->getTitle());
        self::assertStringContainsString('/le-club/textes-officiels/statuts', json_encode($officiels->getBody(), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES), 'Links lead to the new addresses');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $post = $entityManager->getRepository(Post::class)->findOneBy(['slug' => 'trouver-un-thermique']);
        self::assertSame('Vie du club', $post?->getCategory()?->getName());
        self::assertSame('Une méthode.', $post->getExcerpt());
        $media = $entityManager->getRepository(Media::class)->findOneBy(['sourceUrl' => 'https://wp.test/wp-content/uploads/2025/03/nuage.png']);
        self::assertNotNull($media, 'The original picture is imported, not the resized one');
        self::assertSame('Un nuage', $media->getAlt());

        $minutes = $entityManager->getRepository(Post::class)->findOneBy(['slug' => 'reunion-2025-03-05']);
        self::assertSame(Visibility::Groups, $minutes?->getVisibility(), 'Content the anonymous API hides is reserved');
        self::assertFalse($minutes->isAnnounced(), '…and hidden from the other readers');
        self::assertSame(['Comité'], array_map(static fn ($group): string => $group->getName(), $minutes->getAllowedGroups()->toArray()));
        self::assertSame('Comptes rendus du Comité', $minutes->getCategory()?->getName());

        foreach ([
            '/le-club/documents-officiels/statuts/' => '/le-club/textes-officiels/statuts',
            '/2025/03/11/trouver-un-thermique/' => '/actualites/trouver-un-thermique',
            '/login/' => '/connexion',
            '/category/club/' => '/actualites?categorie=vie-du-club',
        ] as $old => $new) {
            $this->client->request('GET', $old);
            self::assertResponseRedirects($new, 301, $old);
        }
    }

    public function testASecondImportUpdates(): void
    {
        $importer = static::getContainer()->get(WordPressImporter::class);
        $importer->import();
        $report = $importer->import();

        self::assertSame(['Pages mises à jour' => 2, 'Articles mis à jour' => 2], $report->counts());
    }

    public function testADryRunSavesNothing(): void
    {
        $report = static::getContainer()->get(WordPressImporter::class)->import(dryRun: true);

        self::assertSame(1, $report->counts()['Fichiers à télécharger'] ?? 0);
        self::assertNull(static::getContainer()->get(PageRepository::class)->findOneByPath('le-club/textes-officiels'));
    }

    /**
     * @param list<int> $categories
     *
     * @return array<string, mixed>
     */
    private static function item(int $id, string $slug, string $link, string $title, string $content, int $parent = 0, array $categories = []): array
    {
        return [
            'id' => $id, 'slug' => $slug, 'link' => $link, 'parent' => $parent, 'menu_order' => 0,
            'title' => ['rendered' => $title], 'content' => ['rendered' => $content], 'excerpt' => ['rendered' => ''],
            'date' => '2025-03-11T17:33:11', 'modified' => '2025-03-11T17:42:08',
            'categories' => $categories, 'sticky' => false, 'featured_media' => 0,
        ];
    }
}
