<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Content\Block as B;
use App\Entity\Page;
use App\Entity\Post;
use App\Entity\User;
use App\Security\Visibility;
use App\Tests\Unit\Flight\Glider\GliderSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * What search engines and link previews see: robots directive, canonical address, Open Graph,
 * robots.txt and sitemap.xml (doc/seo.md).
 */
final class SeoTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testThePublicShowcaseIsIndexedWithItsCanonicalAddress(): void
    {
        // 12 posts a page: two pages
        for ($i = 1; $i <= 13; ++$i) {
            $this->post('article-'.$i);
        }

        $this->client->request('GET', '/actualites?page=2&utm_source=x');

        self::assertResponseIsSuccessful();
        self::assertSame('index, follow, max-image-preview:large', $this->meta('robots'));
        self::assertSame('http://localhost/actualites?page=2', $this->canonical(), 'Only the category and the page are kept');
        self::assertSame('http://localhost/actualites?page=2', $this->meta('og:url'));
        self::assertStringStartsWith('http://localhost/', (string) $this->meta('og:image'));

        $this->client->request('GET', '/actualites?page=1');
        self::assertSame('http://localhost/actualites', $this->canonical());
        self::assertSame('Actualités', $this->meta('og:title'));
    }

    public function testPrivateAreasAreNotIndexed(): void
    {
        $this->client->request('GET', '/connexion');
        self::assertSame('noindex, follow', $this->meta('robots'));

        $this->client->request('GET', '/sessions');
        self::assertSame('noindex, follow', $this->meta('robots'), 'Sessions carry the names of pilots');
    }

    public function testAPostIsDescribedAsAnArticleAndOnlyIndexedWhenPublic(): void
    {
        $post = $this->post('vol-du-mois');
        $restricted = $this->post('reserve')->setVisibility(Visibility::Authenticated);
        $this->entityManager->flush();

        $this->client->request('GET', '/actualites/vol-du-mois');
        self::assertSame('index, follow, max-image-preview:large', $this->meta('robots'));
        self::assertSame('article', $this->meta('og:type'));
        self::assertSame('Résumé de l’article.', $this->meta('og:description'));
        self::assertNotNull($this->meta('article:published_time'));

        $this->client->loginUser($this->user());
        $this->client->request('GET', '/actualites/'.$restricted->getSlug());
        self::assertResponseIsSuccessful();
        self::assertSame('noindex, follow', $this->meta('robots'));
        self::assertSame('vol-du-mois', $post->getSlug());
    }

    public function testAPageUnderARestrictedSectionIsNotIndexed(): void
    {
        $club = $this->page('Le Club', 'le-club');
        $this->page('Présentation', 'presentation', $club);
        $committee = $this->page('Comité', 'comite')->setVisibility(Visibility::Authenticated);
        $this->page('Comptes rendus', 'comptes-rendus', $committee);
        $this->entityManager->flush();

        $this->client->request('GET', '/le-club/presentation');
        self::assertSame('index, follow, max-image-preview:large', $this->meta('robots'));
        self::assertSame('http://localhost/le-club/presentation', $this->canonical());

        $this->client->loginUser($this->user());
        $this->client->request('GET', '/comite/comptes-rendus');
        self::assertResponseIsSuccessful();
        self::assertSame('noindex, follow', $this->meta('robots'));
    }

    public function testTheSearchSettingsReplaceTheTitleTheLeadAndTheCover(): void
    {
        $this->post('vol-du-mois')->setSeoTitle('Le vol du mois en planeur')->setSeoDescription('Un vol de 300 km dans les Alpes.');
        $this->post('hors-moteurs')->setNoIndex(true);
        $this->entityManager->flush();

        $this->client->request('GET', '/actualites/vol-du-mois');
        self::assertSelectorTextSame('title', 'Le vol du mois en planeur · Club ePlaneur');
        self::assertSelectorTextSame('h1', 'Article vol-du-mois', 'The page keeps its title');
        self::assertSame('Un vol de 300 km dans les Alpes.', $this->meta('description'));
        self::assertSame('Le vol du mois en planeur', $this->meta('og:title'));

        $this->client->request('GET', '/actualites/hors-moteurs');
        self::assertSame('noindex, follow', $this->meta('robots'));
        $this->client->request('GET', '/sitemap.xml');
        self::assertStringNotContainsString('/actualites/hors-moteurs', (string) $this->client->getResponse()->getContent());
    }

    public function testStructuredDataDescribeTheClubTheArticlesAndTheBreadcrumbs(): void
    {
        $this->client->request('GET', '/');
        $graph = $this->jsonLd();
        self::assertSame(['Organization', 'WebSite'], array_column($graph, '@type'));
        self::assertSame('Club ePlaneur', $graph[0]['name']);
        self::assertMatchesRegularExpression('#^http://localhost/assets/images/brand/logo-512-\w+\.png$#', $graph[0]['logo']['url']);
        self::assertContains('https://www.facebook.com/groups/eplaneur', $graph[0]['sameAs']);
        self::assertSame('http://localhost/#organization', $graph[1]['publisher']['@id']);

        $this->post('l-art-du-thermique')->setSeoTitle('L’art du thermique « </script> »')->setKeywords(['thermique', 'spirale']);
        $this->entityManager->flush();
        $this->client->request('GET', '/actualites/l-art-du-thermique');
        $graph = $this->jsonLd();
        self::assertSame('BlogPosting', $graph[0]['@type']);
        self::assertSame('L’art du thermique « </script> »', $graph[0]['headline'], 'Escaped in the page, intact once read');
        self::assertSame('Résumé de l’article.', $graph[0]['description']);
        self::assertSame('thermique, spirale', $graph[0]['keywords']);
        self::assertSame(['@id' => 'http://localhost/#organization'], $graph[0]['author'], 'No author: the club');
        self::assertSame(['Accueil', 'Actualités', 'Article l-art-du-thermique'], array_column($graph[1]['itemListElement'], 'name'));
        self::assertSame('http://localhost/actualites', $graph[1]['itemListElement'][1]['item']);

        $club = $this->page('Le Club', 'le-club');
        $this->page('Présentation', 'presentation', $club);
        $this->client->request('GET', '/le-club/presentation');
        $graph = $this->jsonLd();
        self::assertSame(['http://localhost/', 'http://localhost/le-club', 'http://localhost/le-club/presentation'], array_column($graph[0]['itemListElement'], 'item'));
    }

    public function testFaqAndGlossaryBlocksAreDescribedForAnswerEngines(): void
    {
        $blocks = static::getContainer()->get(B\BlockFactory::class);
        $this->page('Questions', 'questions')->setBody($blocks->serializeAll([
            new B\FaqBlock([new B\FaqItem('Faut-il un joystick ?', '<p>Oui, ou un manche.</p>')]),
            new B\GlossaryBlock([new B\Fact('Finesse', 'Distance parcourue pour un mètre de hauteur perdue.')], 'Lexique'),
            new B\FaqBlock([new B\FaqItem('Quel simulateur ?', '<p>Condor 3.</p>')]),
        ]));
        $this->entityManager->flush();

        $this->client->request('GET', '/questions');

        $graph = $this->jsonLd();
        self::assertSame(['BreadcrumbList', 'FAQPage', 'DefinedTermSet'], array_column($graph, '@type'));
        self::assertSame(['Faut-il un joystick ?', 'Quel simulateur ?'], array_column($graph[1]['mainEntity'], 'name'), 'One FAQPage for all the questions');
        self::assertSame('<p>Oui, ou un manche.</p>', $graph[1]['mainEntity'][0]['acceptedAnswer']['text']);
        self::assertSame(['@type' => 'DefinedTerm', 'name' => 'Finesse', 'description' => 'Distance parcourue pour un mètre de hauteur perdue.'], $graph[2]['hasDefinedTerm'][0]);
    }

    public function testRobotsTxtWelcomesEveryCrawlerAndGivesTheSitemap(): void
    {
        $this->client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString("User-agent: *\n", $content);
        self::assertStringContainsString("Disallow: /mon-compte\n", $content);
        self::assertStringNotContainsString('Disallow: /'."\n", $content, 'The site is open');
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $content);
    }

    public function testAStagingCopyKeepsSearchEnginesOut(): void
    {
        $this->post('vol-du-mois');
        $this->entityManager->flush();
        $previous = [$_ENV['SEARCH_ENGINES_ALLOWED'] ?? null, $_SERVER['SEARCH_ENGINES_ALLOWED'] ?? null];
        $_ENV['SEARCH_ENGINES_ALLOWED'] = $_SERVER['SEARCH_ENGINES_ALLOWED'] = '0';
        try {
            self::ensureKernelShutdown();
            $client = static::createClient();

            $client->request('GET', '/actualites/vol-du-mois');
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
            self::assertSame('noindex, follow', $client->getCrawler()->filter('meta[name="robots"]')->attr('content'));

            $client->request('GET', '/robots.txt');
            self::assertSame("User-agent: *\nDisallow: /\n", (string) $client->getResponse()->getContent());

            $client->request('GET', '/sitemap.xml');
            self::assertResponseStatusCodeSame(404);
            $client->request('GET', '/llms.txt');
            self::assertResponseStatusCodeSame(404);
        } finally {
            [$_ENV['SEARCH_ENGINES_ALLOWED'], $_SERVER['SEARCH_ENGINES_ALLOWED']] = $previous;
        }
    }

    public function testTheSitemapListsOnlyWhatMayBeIndexed(): void
    {
        $club = $this->page('Le Club', 'le-club');
        $this->page('Présentation', 'presentation', $club);
        $this->page('Brouillon', 'brouillon', $club, published: false);
        $committee = $this->page('Comité', 'comite')->setVisibility(Visibility::Groups);
        $this->page('Comptes rendus', 'comptes-rendus', $committee);
        $this->post('vol-du-mois');
        $this->post('reserve')->setVisibility(Visibility::Authenticated);
        $this->post('a-venir', published: false);
        $this->entityManager->persist(GliderSamples::glider('Diana2'));
        $this->entityManager->flush();

        $this->client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        $xml = new \SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $locations = array_map(static fn (\SimpleXMLElement $url): string => (string) $url->loc, iterator_to_array($xml->url, false));
        foreach (['/', '/actualites', '/le-club', '/le-club/presentation', '/actualites/vol-du-mois', '/planeurs', '/planeurs/Diana2'] as $path) {
            self::assertContains('http://localhost'.('/' === $path ? '/' : $path), $locations);
        }
        foreach (['/le-club/brouillon', '/comite', '/comite/comptes-rendus', '/actualites/reserve', '/actualites/a-venir'] as $path) {
            self::assertNotContains('http://localhost'.$path, $locations);
        }
    }

    public function testLlmsTxtSummarisesThePublicSite(): void
    {
        $club = $this->page('Le Club', 'le-club');
        $this->page('Présentation', 'presentation', $club)->setExcerpt("Qui sommes-nous ?\nUne association <em>en ligne</em> & ouverte.");
        $this->page('Comité', 'comite')->setVisibility(Visibility::Authenticated);
        $this->post('vol-du-mois');
        $this->entityManager->flush();

        $this->client->request('GET', '/llms.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith('# Club ePlaneur', $content);
        self::assertStringContainsString("- [Le Club](http://localhost/le-club)\n  - [Présentation](http://localhost/le-club/presentation): Qui sommes-nous ? Une association en ligne & ouverte.\n", $content);
        self::assertStringContainsString('- [Article vol-du-mois](http://localhost/actualites/vol-du-mois): Résumé de l’article.', $content);
        self::assertStringNotContainsString('comite', $content);
    }

    private function meta(string $name): ?string
    {
        $meta = $this->client->getCrawler()->filter(\sprintf('meta[name="%1$s"], meta[property="%1$s"]', $name));

        return $meta->count() > 0 ? $meta->attr('content') : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLd(): array
    {
        $script = $this->client->getCrawler()->filter('script[type="application/ld+json"]');
        self::assertCount(1, $script);
        self::assertStringNotContainsString('</script>', $script->html());

        return json_decode($script->text(), true, flags: \JSON_THROW_ON_ERROR)['@graph'];
    }

    private function canonical(): ?string
    {
        $link = $this->client->getCrawler()->filter('link[rel="canonical"]');

        return $link->count() > 0 ? $link->attr('href') : null;
    }

    private function page(string $title, string $slug, ?Page $parent = null, bool $published = true): Page
    {
        $page = (new Page($title, $slug))->setParent($parent)->setPublishedAt($published ? new \DateTimeImmutable('-1 day') : null);
        $parent?->getChildren()->add($page);
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }

    private function post(string $slug, bool $published = true): Post
    {
        $post = (new Post('Article '.$slug, $slug))
            ->setExcerpt('Résumé de l’article.')
            ->setPublishedAt($published ? new \DateTimeImmutable('-1 day') : null);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }

    private function user(): User
    {
        $user = (new User())->setEmail('lecteur@example.org')->setDisplayName('Lecteur')->setVerified(true)->setPassword('x');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
