<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\GroupRepository;
use App\Security\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * News list (/actualites) and the "Ça bouge au club" section of the home page: featured post, filters
 * by category and period, pagination, reserved posts.
 */
final class NewsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testTheListShowsTheFeaturedPostThenTheOthers(): void
    {
        $club = $this->category('Vie du club', 'vie-du-club');
        $this->post('voeux-2026', '2026-01-01 09:00', $club, featured: true);
        $this->post('championnat', '2025-11-21 18:00', $club);
        $this->post('brouillon', null, $club);
        $this->post('programme', '+3 days', $club);

        $crawler = $this->client->request('GET', '/actualites');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.c-featured-post', 'Article voeux-2026');
        $cards = $crawler->filter('.p-news-list__grid .c-post-card__title')->each(static fn ($node): string => $node->text());
        self::assertContains('Article championnat', $cards);
        self::assertNotContains('Article voeux-2026', $cards, 'The featured post is not repeated');
        self::assertNotContains('Article brouillon', $cards, 'Drafts are not listed');
        self::assertNotContains('Article programme', $cards, 'Scheduled posts are not listed yet');
        self::assertSelectorTextContains('.p-news-list__categories', 'Vie du club · 2');
    }

    public function testPostsAreFilteredByCategoryAndPeriod(): void
    {
        $club = $this->category('Vie du club', 'vie-du-club');
        $guides = $this->category('Guide pratique', 'guide-pratique');
        $this->post('club-octobre', '2025-10-12 10:00', $club);
        $this->post('club-mars', '2025-03-02 10:00', $club);
        $this->post('guide-octobre', '2025-10-20 10:00', $guides);
        // Just after midnight in Paris: November, although still October in UTC
        $this->post('club-novembre', '2025-11-01 00:30', $club);

        $crawler = $this->client->request('GET', '/actualites?categorie=vie-du-club&annee=2025&mois=10');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.c-featured-post', 'The featured post only leads the unfiltered list');
        self::assertSame(['Article club-octobre'], $crawler->filter('.p-news-list__grid .c-post-card__title')->each(static fn ($node): string => $node->text()));
        self::assertSelectorTextContains('#news-title', 'Vie du club · octobre 2025 · 1 article');

        $crawler = $this->client->request('GET', '/actualites?annee=2025&mois=11');
        self::assertSame(['Article club-novembre'], $crawler->filter('.p-news-list__grid .c-post-card__title')->each(static fn ($node): string => $node->text()), 'Periods follow metropolitan France time');

        $this->client->request('GET', '/actualites?annee=2024');
        self::assertSelectorTextContains('.p-news-list__empty', 'Aucun article pour cette période.');
    }

    public function testUnknownFiltersAndPagesAreNotFound(): void
    {
        foreach (['/actualites?categorie=inconnue', '/actualites?annee=abc', '/actualites?mois=13&annee=2025', '/actualites?page=2'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testTheListIsPaginated(): void
    {
        for ($i = 1; $i <= 14; ++$i) {
            $this->post('article-'.$i, \sprintf('2025-01-%02d 10:00', $i));
        }

        $crawler = $this->client->request('GET', '/actualites?page=2');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.p-news-list__grid .c-post-card'));
        self::assertSelectorTextContains('.c-pagination [aria-current="page"]', '2');
        self::assertSelectorExists('.c-pagination a[rel="prev"][href="/actualites"]');
    }

    public function testReservedPostsShowTheirAudienceToVisitors(): void
    {
        $post = $this->post('compte-rendu', '2025-09-30 20:00');
        $post->setVisibility(Visibility::Groups)->addAllowedGroup(static::getContainer()->get(GroupRepository::class)->findOneByCode('committee'));
        $this->entityManager->flush();

        $this->client->request('GET', '/actualites');

        self::assertSelectorExists('.c-post-card--locked');
        self::assertSelectorTextContains('.c-post-card--locked', 'Comité');
        self::assertSelectorTextContains('.c-post-card--locked', 'Se connecter pour lire');
        self::assertSelectorTextNotContains('.c-post-card--locked', 'Résumé de compte-rendu', 'The excerpt stays hidden');
    }

    public function testHiddenReservedPostsOnlyAppearToTheirReaders(): void
    {
        $minutes = $this->post('compte-rendu-cd', '2025-09-30 20:00', featured: true);
        $minutes->setVisibility(Visibility::Groups)->addAllowedGroup(static::getContainer()->get(GroupRepository::class)->findOneByCode('committee'))->setAnnounced(false);
        $this->post('public', '2025-09-01 20:00');
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/actualites');
        self::assertSelectorNotExists('.c-featured-post', 'Not even as the featured post');
        self::assertSame(['Article public'], $crawler->filter('.c-post-card__title')->each(static fn ($node): string => $node->text()));
        self::assertSelectorTextContains('#news-title', '1 article');
        $this->client->request('GET', '/');
        self::assertSelectorTextNotContains('#actualites', 'compte-rendu-cd');

        $committee = (new User())->setEmail('comite@example.org')->setDisplayName('Comité')->setVerified(true)->setPassword('x')
            ->addGroup(static::getContainer()->get(GroupRepository::class)->findOneByCode('committee'));
        $this->entityManager->persist($committee);
        $this->entityManager->flush();
        $this->client->loginUser($committee);
        $this->client->request('GET', '/actualites');
        self::assertSelectorTextContains('.c-featured-post', 'Article compte-rendu-cd', 'The committee sees it, without padlock');
    }

    public function testTheHomePageShowsTheFeaturedAndLatestPosts(): void
    {
        $this->post('ancien', '2025-01-10 10:00');
        $this->post('recent', '2025-06-10 10:00');
        $this->post('plus-recent', '2025-07-10 10:00');
        $this->post('a-la-une', '2025-02-10 10:00', featured: true);

        $crawler = $this->client->request('GET', '/');

        self::assertSelectorTextContains('#actualites .c-featured-post', 'Article a-la-une');
        self::assertSame(['Article plus-recent', 'Article recent'], $crawler->filter('#actualites .c-post-card__title')->each(static fn ($node): string => $node->text()));
        self::assertSelectorExists('#actualites a[href="/actualites"]');
    }

    private function category(string $name, string $slug): Category
    {
        $category = $this->entityManager->getRepository(Category::class)->findOneBy(['slug' => $slug]) ?? new Category($name, $slug);
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $category;
    }

    private function post(string $slug, ?string $publishedAt, ?Category $category = null, bool $featured = false): Post
    {
        $post = (new Post('Article '.$slug, $slug))
            ->setExcerpt('Résumé de '.$slug.'.')
            ->setCategory($category)
            ->setFeatured($featured)
            ->setPublishedAt(null !== $publishedAt ? new \DateTimeImmutable($publishedAt, new \DateTimeZone('Europe/Paris')) : null);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }
}
