<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Content\Block as B;
use App\Entity\Group;
use App\Entity\Media;
use App\Entity\Post;
use App\Entity\User;
use App\Media\MediaStorage;
use App\Repository\GroupRepository;
use App\Security\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Article page: block rendering, table of contents, drafts, restricted posts and media access.
 */
final class PostTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Same kernel for every request: the entities created by the test stay managed
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testStoredUnsafeLinksAreNotRendered(): void
    {
        // Stored before the editor checked link addresses: rendered as "#"
        $this->createPost('lien-ancien', [new B\LinksBlock([new B\LinkItem('Piège', 'javascript:alert(1)'), new B\LinkItem('Club', '/le-club')])]);

        $crawler = $this->client->request('GET', '/actualites/lien-ancien');

        self::assertSame(['#', '/le-club'], $crawler->filter('.p-article__body a.c-link-list__item, .p-article__body .c-link-list a')->each(static fn ($link): ?string => $link->attr('href')));
    }

    public function testArticleRendersItsBlocksAndTableOfContents(): void
    {
        $image = $this->storeMedia('wing-valley.png');
        $file = $this->storeMedia('ma-checklist-de-vol.txt');
        $this->createPost('premier-vol', [
            new B\SectionBlock('Le premier objectif', 'Premières ailes', 'Une intro.', 'Trouver ses repères'),
            new B\TextBlock('<p>Un texte <strong>important</strong><script>alert(1)</script></p>'),
            new B\CalloutBlock('À retenir', '<p>Une séance, une intention.</p>', 'tip'),
            new B\ImageBlock((int) $image->getId(), 'Un horizon dégagé'),
            new B\TabsBlock([new B\Tab('Condor 3', 'Base Condor 3'), new B\Tab('Condor 2', 'Base Condor 2')]),
            new B\ChecklistBlock('Checklist', ['Un', 'Deux']),
            new B\SectionBlock('Deuxième partie'),
            new B\VideoBlock('https://youtu.be/aqz-KE-bpKQ', 'Démo'),
            new B\DownloadsBlock([new B\DownloadItem((int) $file->getId(), 'Ma checklist')]),
            new B\FaqBlock([new B\FaqItem('Question ?', '<p>Réponse.</p>')]),
        ], outro: [new B\TakeawaysBlock('À retenir', points: ['Point'], navTitle: 'L’essentiel')]);

        $crawler = $this->client->request('GET', '/actualites/premier-vol');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Article de test');
        // Table of contents: sections then takeaways, numbered, linked to their anchors
        self::assertSame(['01 Trouver ses repères', '02 Deuxième partie', '03 L’essentiel'], $crawler->filter('.c-toc__link')->each(static fn ($link): string => preg_replace('/\s+/', ' ', trim($link->text()))));
        self::assertSelectorExists('#trouver-ses-reperes');
        self::assertSelectorTextContains('#trouver-ses-reperes .c-eyebrow', '01 / Premières ailes');
        // Rich text is sanitized
        self::assertSelectorTextContains('.c-rich-text strong', 'important');
        self::assertStringNotContainsString('<script>alert(1)</script>', (string) $this->client->getResponse()->getContent());
        self::assertSelectorExists('.c-admonition--tip');
        self::assertSelectorExists('.c-block-figure img[src^="/media/'.$image->getId().'/"]');
        self::assertCount(2, $crawler->filter('[role="tab"]'));
        self::assertCount(2, $crawler->filter('.c-checklist input[type="checkbox"]'));
        // The player is only loaded after a click
        self::assertSelectorNotExists('.c-video iframe');
        self::assertSelectorExists('[data-video-src-value="https://www.youtube-nocookie.com/embed/aqz-KE-bpKQ?autoplay=1&rel=0"]');
        self::assertSelectorTextContains('.c-download__meta', 'TXT');
        self::assertSelectorExists('details.c-faq__item[open]');
        self::assertSelectorTextContains('.c-takeaways', 'Point');
    }

    public function testBrokenBlocksAreSkippedWithoutBreakingThePage(): void
    {
        $post = $this->createPost('bloc-casse', []);
        $post->setBody([
            ['type' => 'text', 'data' => ['html' => '<p>Avant</p>']],
            ['type' => 'does-not-exist', 'data' => []],
            ['type' => 'image', 'data' => ['mediaId' => 999999]],
            ['type' => 'heading', 'data' => []],
            ['type' => 'text', 'data' => ['html' => '<p>Après</p>']],
        ]);
        $this->entityManager->flush();

        $this->client->request('GET', '/actualites/bloc-casse');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-article__body', 'Avant');
        self::assertSelectorTextContains('.p-article__body', 'Après');
    }

    public function testDraftsAreOnlyVisibleToEditors(): void
    {
        $this->createPost('brouillon', [new B\TextBlock('<p>Brouillon</p>')], published: false);

        $this->client->request('GET', '/actualites/brouillon');
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->createUser('membre@example.org', 'member'));
        $this->client->request('GET', '/actualites/brouillon');
        self::assertResponseStatusCodeSame(404);

        // The committee holds POST_EDIT by default
        $this->client->loginUser($this->createUser('comite@example.org', 'committee'));
        $this->client->request('GET', '/actualites/brouillon');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-article__preview', 'n’est pas publié');
    }

    public function testRestrictedPostNeedsTheRightGroup(): void
    {
        $post = $this->createPost('compte-rendu', [new B\TextBlock('<p>Secret</p>')]);
        $post->setVisibility(Visibility::Groups)->addAllowedGroup($this->group('committee'));
        $this->entityManager->flush();

        $this->client->request('GET', '/actualites/compte-rendu');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('.c-restricted__title', 'Contenu réservé au groupe Comité');
        self::assertSelectorTextNotContains('body', 'Secret');

        $this->client->loginUser($this->createUser('membre@example.org', 'member'));
        $this->client->request('GET', '/actualites/compte-rendu');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('.c-restricted', 'votre compte n’a pas accès');

        $this->client->loginUser($this->createUser('comite@example.org', 'committee'));
        $this->client->request('GET', '/actualites/compte-rendu');
        self::assertResponseIsSuccessful();
    }

    public function testRelatedPostsOnlyListPostsTheReaderMaySee(): void
    {
        $this->createPost('principal', [new B\TextBlock('<p>Texte</p>')]);
        $this->createPost('public', [new B\TextBlock('<p>Texte</p>')], title: 'Article public lié');
        $restricted = $this->createPost('reserve', [new B\TextBlock('<p>Texte</p>')], title: 'Article réservé lié');
        $restricted->setVisibility(Visibility::Authenticated);
        $this->entityManager->flush();

        $this->client->request('GET', '/actualites/principal');

        self::assertSelectorTextContains('.p-article__related', 'Article public lié');
        self::assertSelectorTextNotContains('.p-article__related', 'Article réservé lié');
    }

    public function testMediaAccess(): void
    {
        $public = $this->storeMedia('ma-checklist-de-vol.txt');
        $private = $this->storeMedia('briefing-premieres-ailes.pdf')->setVisibility(Visibility::Groups)->addAllowedGroup($this->group('committee'));
        $this->entityManager->flush();

        $this->client->request('GET', '/media/'.$public->getId().'/ma-checklist-de-vol.txt');
        self::assertResponseIsSuccessful();
        self::assertStringStartsWith('text/plain', (string) $this->client->getResponse()->headers->get('Content-Type'));
        // Not an image or a PDF: always downloaded, never displayed by the browser
        self::assertStringStartsWith('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/media/'.$private->getId().'/briefing.pdf');
        self::assertResponseRedirects('/connexion');

        $this->client->loginUser($this->createUser('comite@example.org', 'committee'));
        $this->client->request('GET', '/media/'.$private->getId().'/briefing.pdf');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/media/999999/inconnu.png');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param list<B\BlockInterface> $body
     * @param list<B\BlockInterface> $outro
     */
    private function createPost(string $slug, array $body, bool $published = true, array $outro = [], string $title = 'Article de test'): Post
    {
        $blocks = static::getContainer()->get(B\BlockFactory::class);
        $post = (new Post($title, $slug))
            ->setExcerpt('Résumé de l’article.')
            ->setPublishedAt($published ? new \DateTimeImmutable('-1 day') : null)
            ->setBody($blocks->serializeAll($body))
            ->setOutro($blocks->serializeAll($outro));
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }

    private function storeMedia(string $fixture): Media
    {
        $media = static::getContainer()->get(MediaStorage::class)->storeCopy(\dirname(__DIR__, 2).'/fixtures/media/'.$fixture);
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    private function createUser(string $email, string $groupCode): User
    {
        $user = (new User())->setEmail($email)->setDisplayName('Test')->setVerified(true)->setPassword('x')->addGroup($this->group($groupCode));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function group(string $code): Group
    {
        $group = static::getContainer()->get(GroupRepository::class)->findOneByCode($code);
        self::assertNotNull($group, 'Default groups missing: run make test-db.');

        return $group;
    }
}
