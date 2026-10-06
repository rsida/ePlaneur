<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\PageCrudController;
use App\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Page tree and page editor: sub-pages, blocks of pages, deletion of a page with sub-pages.
 *
 * @extends AbstractCrudTestCase<PageCrudController>
 */
final class PageCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return PageCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testTheListIsTheTreeOfPages(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $section = $this->createPage('Rubrique de test', 'rubrique-test');
        $this->createPage('Sous-page de test', 'sous-page', $section);

        $crawler = $this->client->request('GET', $this->generateIndexUrl());

        self::assertResponseIsSuccessful();
        $row = $crawler->filter('tr[data-title="sous-page de test"]');
        self::assertCount(1, $row);
        self::assertSame((string) $section->getId(), $row->attr('data-ancestors'));
        self::assertStringContainsString('/rubrique-test/sous-page', $row->text());
    }

    public function testASubPageIsCreatedWithItsBlocks(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $section = $this->createPage('Rubrique de test', 'rubrique-test');

        $crawler = $this->client->request('GET', $this->generateNewFormUrl().'?parent='.$section->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.ep-library__item[data-type="child_pages"]');
        $form = $crawler->filter('form[name="page_editor"]')->form();
        self::assertSame((string) $section->getId(), $form['page_editor[parent]']->getValue(), 'The parent comes from the tree');

        $values = $form->getPhpValues();
        $values['page_editor']['title'] = 'Nouvelle page';
        $values['page_editor']['slug'] = 'nouvelle-page';
        $values['page_editor']['aside'] = json_encode([['id' => 'b1', 'type' => 'resource', 'data' => ['title' => 'À lire']]]);
        $this->client->request('POST', $form->getUri(), $values);

        self::assertResponseRedirects();
        $page = $this->entityManager->getRepository(Page::class)->findOneBy(['slug' => 'nouvelle-page']);
        self::assertSame($section->getId(), $page?->getParent()?->getId());
        self::assertSame('rubrique-test/nouvelle-page', $page->getPath());
        self::assertSame([['type' => 'resource', 'data' => ['title' => 'À lire']]], $page->getAside());
    }

    public function testSavingDatesThePageUnlessAnotherDateIsChosen(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $page = $this->createPage('Membres inscrits', 'membres-inscrits')->setUpdatedAt(new \DateTimeImmutable('2026-10-01 10:00'));
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', $this->generateEditFormUrl($page->getId()));
        $this->client->submit($crawler->filter('form[name="page_editor"]')->form());
        self::assertResponseRedirects();
        $this->entityManager->clear();
        $updatedAt = $this->entityManager->getRepository(Page::class)->find($page->getId())?->getUpdatedAt();
        self::assertSame((new \DateTimeImmutable())->format('Y-m-d'), $updatedAt?->format('Y-m-d'), 'An unchanged date becomes today');

        $crawler = $this->client->request('GET', $this->generateEditFormUrl($page->getId()));
        $this->client->submit($crawler->filter('form[name="page_editor"]')->form(['page_editor[updatedAt]' => '2026-09-15']));
        self::assertResponseRedirects();
        $this->entityManager->clear();
        $updatedAt = $this->entityManager->getRepository(Page::class)->find($page->getId())?->getUpdatedAt();
        self::assertSame('2026-09-15', $updatedAt?->format('Y-m-d'), 'A chosen date is kept');
    }

    public function testAPageWithSubPagesIsNotDeleted(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $section = $this->createPage('Rubrique de test', 'rubrique-test');
        $this->createPage('Sous-page de test', 'sous-page', $section);

        $crawler = $this->client->request('GET', $this->generateIndexUrl());
        $token = $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $this->client->request('POST', '/admin/page/'.$section->getId().'/delete', ['token' => $token]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'a des sous-pages');
        self::assertNotNull($this->entityManager->getRepository(Page::class)->find($section->getId()));
    }

    private function createPage(string $title, string $slug, ?Page $parent = null): Page
    {
        $page = (new Page($title, $slug))->setParent($parent)->setPublishedAt(new \DateTimeImmutable('-1 day'));
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }
}
