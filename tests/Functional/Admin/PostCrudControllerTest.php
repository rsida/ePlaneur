<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\PostCrudController;
use App\Entity\Group;
use App\Entity\Post;
use App\Entity\User;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Post list and block editor: saving blocks, invalid blocks, canvas rendering, rights of authors
 * and publication.
 *
 * @extends AbstractCrudTestCase<PostCrudController>
 */
final class PostCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return PostCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testTheListShowsThePublicationState(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $this->createPost('brouillon', null);
        $this->createPost('programme', new \DateTimeImmutable('+2 days'));

        $this->client->request('GET', $this->generateIndexUrl());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Brouillon');
        self::assertSelectorTextContains('body', 'Programmé');
    }

    public function testTheEditorSavesTheBlocksOfANewPost(): void
    {
        $user = $this->userIn($this->entityManager, 'comite@example.org', 'committee');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', $this->generateNewFormUrl());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('iframe.ep-editor__frame');
        self::assertSelectorExists('.ep-library__item[data-type="callout"]');
        self::assertSelectorNotExists('.ep-library__item[data-type="child_pages"]', 'Sub-pages only make sense on pages');

        $form = $crawler->filter('form[name="post_editor"]')->form();
        $values = $form->getPhpValues();
        $values['post_editor']['title'] = 'Premier essai';
        $values['post_editor']['slug'] = 'premier-essai';
        $values['post_editor']['excerpt'] = 'Un essai.';
        // The editor sends its block ids: they are not stored
        $values['post_editor']['body'] = json_encode([
            ['id' => 'b1', 'type' => 'callout', 'data' => ['title' => 'À retenir', 'html' => '<p>Texte</p>', 'variant' => 'tip', 'showIcon' => true]],
            ['id' => 'b2', 'type' => 'heading', 'data' => ['text' => 'Sous-titre', 'size' => 'md', 'unknown' => 'dropped']],
        ]);
        $values['post_editor']['publishedAt'] = '';
        $this->client->request('POST', $form->getUri(), $values);

        $post = $this->entityManager->getRepository(Post::class)->findOneBy(['slug' => 'premier-essai']);
        self::assertNotNull($post);
        self::assertResponseRedirects('/admin/post/'.$post->getId().'/edit');
        self::assertSame([
            ['type' => 'callout', 'data' => ['title' => 'À retenir', 'html' => '<p>Texte</p>', 'variant' => 'tip', 'showIcon' => true]],
            ['type' => 'heading', 'data' => ['text' => 'Sous-titre', 'size' => 'md']],
        ], $post->getBody());
        self::assertSame($user->getId(), $post->getAuthor()?->getId(), 'The writer is the default author');
        self::assertNull($post->getPublishedAt());
    }

    public function testAnInvalidBlockIsRefused(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $post = $this->createPost('a-modifier', null);

        $crawler = $this->client->request('GET', $this->generateEditFormUrl($post->getId()));
        $form = $crawler->filter('form[name="post_editor"]')->form();
        $values = $form->getPhpValues();
        $values['post_editor']['body'] = json_encode([['id' => 'b1', 'type' => 'callout', 'data' => ['variant' => 'tip']]]);
        $this->client->request('POST', $form->getUri(), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.ep-editor__errors', 'Bloc 1 (Encadré) : contenu invalide.');
    }

    public function testTheCanvasRendersBlocksWithEditableValues(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $post = $this->createPost('canevas', null);

        $this->client->request('GET', '/admin/post/canvas');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-ep-zone="body"]');

        $this->client->request('POST', '/admin/post/render?id='.$post->getId(), [
            'post_editor' => ['title' => 'Titre en cours'],
            'zones' => json_encode([
                'body' => [
                    ['id' => 'b1', 'type' => 'section', 'data' => ['title' => 'Première partie']],
                    ['id' => 'b2', 'type' => 'callout', 'data' => ['title' => 'À retenir', 'html' => '<p>Texte</p>']],
                    ['id' => 'b3', 'type' => 'callout', 'data' => []],
                ],
            ]),
        ]);

        self::assertResponseIsSuccessful();
        /** @var array{header: string, toc: string, blocks: array<string, string>} $rendered */
        $rendered = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertStringContainsString('Titre en cours', $rendered['header'], 'Unsaved settings are rendered');
        self::assertStringContainsString('data-ep-meta="title"', $rendered['header']);
        self::assertStringContainsString('<p class="c-eyebrow">01</p>', $rendered['blocks']['b1'], 'Sections are numbered');
        self::assertStringContainsString('data-ep-field="title"', $rendered['blocks']['b2']);
        self::assertStringContainsString('data-ep-field="html" data-ep-kind="rich"', $rendered['blocks']['b2']);
        self::assertStringContainsString('illisible', $rendered['blocks']['b3']);
        self::assertStringContainsString('Première partie', $rendered['toc']);
        self::assertSame('canevas', $this->entityManager->getRepository(Post::class)->find($post->getId())?->getSlug());
    }

    public function testAuthorsOnlyEditTheirOwnPostsAndCannotPublish(): void
    {
        $writers = (new Group('writers', 'Rédaction'))->setPermissions([Permission::AdminAccess, Permission::PostCreate]);
        $this->entityManager->persist($writers);
        $author = (new User())->setEmail('auteur@example.org')->setDisplayName('Auteur')->setVerified(true)->setPassword('x')->addGroup($writers);
        $this->entityManager->persist($author);
        $own = $this->createPost('le-mien', null)->setAuthor($author);
        $other = $this->createPost('un-autre', null);
        $this->entityManager->flush();
        $this->client->loginUser($author);

        $crawler = $this->client->request('GET', $this->generateEditFormUrl($own->getId()));
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[name="post_editor[publishedAt]"]'), 'POST_PUBLISH is needed to publish');

        $this->client->request('GET', $this->generateEditFormUrl($other->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testPublishingSetsTheDate(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $post = $this->createPost('a-publier', null);

        $crawler = $this->client->request('GET', $this->generateEditFormUrl($post->getId()));
        $form = $crawler->filter('form[name="post_editor"]')->form();
        $values = $form->getPhpValues();
        $values['post_editor']['publishedAt'] = '2026-10-12T20:30';
        $this->client->request('POST', $form->getUri(), $values);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Article programmé : il paraîtra le 12/10/2026 à 20:30.');
        $publishedAt = $this->entityManager->getRepository(Post::class)->find($post->getId())?->getPublishedAt();
        self::assertSame('2026-10-12 18:30', $publishedAt?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i'), 'Entered in metropolitan France time');
    }

    private function createPost(string $slug, ?\DateTimeImmutable $publishedAt): Post
    {
        $post = (new Post('Article '.$slug, $slug))->setExcerpt('Résumé.')->setPublishedAt($publishedAt);
        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $post;
    }
}
