<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Content\Block as B;
use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Group;
use App\Entity\MenuItem;
use App\Entity\Page;
use App\Entity\User;
use App\Media\MediaStorage;
use App\Navigation\MenuLocation;
use App\Repository\GroupRepository;
use App\Security\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Page tree, "Contenu réservé" page, menus filtered by rights and official documents.
 */
final class PageTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        // Start from empty menus (the migration seeds default links)
        $this->entityManager->createQuery('DELETE FROM '.MenuItem::class)->execute();
    }

    public function testPagesAreReachedThroughTheirPathWithBreadcrumbAndChildren(): void
    {
        $club = $this->page('Le Club', 'le-club');
        $texts = $this->page('Textes officiels', 'textes-officiels', $club, [new B\TextBlock('<p>Les textes du club.</p>')]);
        $this->page('Statuts', 'statuts', $texts)->setLinkLabel('Lire les statuts');
        $this->page('Brouillon', 'brouillon', $texts, published: false);
        $this->page('Adhésions', 'adhesions', $club);
        $club->setHighlight("Chacun son rythme.\nLa même envie de ciel.");
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/le-club/textes-officiels');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Textes officiels');
        self::assertSame(['Accueil', 'Le Club', 'Textes officiels'], $crawler->filter('.c-breadcrumb__item')->each(static fn ($item): string => trim($item->text())));
        self::assertSelectorTextContains('.p-article__body', 'Les textes du club.');
        // Child pages as cards, drafts excluded
        self::assertSame(['Statuts'], $crawler->filter('.c-page-card__title')->each(static fn ($title): string => trim($title->text())));
        self::assertSelectorTextContains('.c-page-card__link', 'Lire les statuts');
        self::assertSelectorTextContains('.p-article__highlight', 'Chacun son rythme.', 'The highlight is inherited from the section');
        self::assertSame(['Textes officiels', 'Adhésions'], $crawler->filter('.c-subnav__link')->each(static fn ($link): string => trim($link->text())), 'Pages of the section');
        self::assertSelectorTextContains('.c-subnav__link[aria-current="page"]', 'Textes officiels');
        self::assertSelectorTextContains('.p-page__updated', 'Mis à jour le');

        $this->client->request('GET', '/le-club/inconnue');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/textes-officiels');
        self::assertResponseStatusCodeSame(404, 'A page is only reachable under its parent');
    }

    public function testDraftPagesAreOnlyVisibleToPageManagers(): void
    {
        $this->page('Brouillon', 'brouillon', published: false);

        $this->client->request('GET', '/brouillon');
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($this->user('comite@example.org', 'committee'));
        $this->client->request('GET', '/brouillon');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-article__preview', 'pas publiée');
    }

    public function testRestrictedPageShowsTheReservedContentPage(): void
    {
        $activities = $this->page('Activités', 'activites');
        $meetings = $this->page('Réunions du CD', 'reunions-cd', $activities, [new B\TextBlock('<p>Secret</p>')]);
        $meetings->setVisibility(Visibility::Groups)->addAllowedGroup($this->group('committee'));
        $this->entityManager->flush();

        $this->client->request('GET', '/activites/reunions-cd');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('h1', 'Réunions du CD');
        self::assertSelectorTextContains('.c-restricted__title', 'Contenu réservé au groupe Comité');
        self::assertSelectorTextContains('.c-restricted .c-access', 'Comité');
        self::assertSelectorExists('a[href="/connexion?_target_path=/activites/reunions-cd"]');
        self::assertSelectorTextNotContains('body', 'Secret');

        $this->client->loginUser($this->user('membre@example.org', 'member'));
        $this->client->request('GET', '/activites/reunions-cd');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('.c-restricted', 'votre compte n’a pas accès');
        self::assertSelectorExists('.c-restricted a[href="/activites"]');

        $this->client->loginUser($this->user('comite@example.org', 'committee'));
        $this->client->request('GET', '/activites/reunions-cd');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.p-article__body', 'Secret');
    }

    public function testAPageUnderARestrictedPageIsRestrictedToo(): void
    {
        $private = $this->page('Espace comité', 'espace-comite');
        $private->setVisibility(Visibility::Authenticated);
        $this->page('Page publique dessous', 'dessous', $private);
        $this->entityManager->flush();

        $this->client->request('GET', '/espace-comite/dessous');

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('h1', 'Page publique dessous');
        self::assertSelectorTextContains('.c-restricted__title', 'aux personnes connectées');
    }

    public function testPageValidation(): void
    {
        $validator = static::getContainer()->get(ValidatorInterface::class);

        self::assertCount(1, $validator->validate(new Page('Actualités', 'actualites')), 'Reserved root slug');
        self::assertCount(0, $validator->validate((new Page('Actualités du club', 'actualites'))->setParent(new Page('Le Club', 'le-club'))));

        $level = null;
        for ($depth = 1; $depth <= Page::MAX_DEPTH + 1; ++$depth) {
            $level = (new Page('Niveau '.$depth, 'niveau-'.$depth))->setParent($level);
        }
        self::assertGreaterThan(0, \count($validator->validate($level)), 'Too deep');
    }

    public function testAPageWithChildrenCannotBeDeleted(): void
    {
        $parent = $this->page('Parent', 'parent');
        $this->page('Enfant', 'enfant', $parent);

        $this->expectException(\LogicException::class);
        $this->entityManager->remove($parent);
        $this->entityManager->flush();
    }

    public function testMenusOnlyShowLinksTheReaderMaySee(): void
    {
        $club = $this->page('Le Club', 'le-club');
        $meetings = $this->page('Réunions', 'reunions', $club);
        $meetings->setVisibility(Visibility::Groups)->addAllowedGroup($this->group('committee'));
        $draft = $this->page('Brouillon', 'brouillon', $club, published: false);

        $clubLink = $this->link('Le Club', page: $club);
        $this->link('Réunions', $clubLink, page: $meetings);
        $this->link('Brouillon', $clubLink, page: $draft);
        $this->link('Actualités', url: '/actualites');
        $membersOnly = $this->link('Outils', url: 'https://www.eplaneur.fr');
        $membersOnly->setVisibility(Visibility::Groups)->addAllowedGroup($this->group('member'));
        $heading = $this->link('Rubrique sans lien');
        $this->link('Réunions encore', $heading, page: $meetings);
        $footerColumn = $this->link('Colonne', location: MenuLocation::Footer);
        $this->link('Mentions légales', $footerColumn, url: '/mentions-legales', location: MenuLocation::Footer);
        $this->entityManager->flush();

        $this->client->request('GET', '/le-club');
        self::assertSame(['Le Club', 'Actualités', 'Rubrique sans lien'], $this->headerLinks(), 'Links restricted by their own visibility are hidden');
        self::assertSelectorTextContains('.c-nav__trigger[data-active]', 'Le Club');
        self::assertSelectorExists('.c-mega a[href="/le-club/reunions"]', 'A reserved page stays listed…');
        self::assertSelectorTextContains('.c-mega .c-access', 'Comité', '…with its access tag');
        self::assertSelectorNotExists('.c-mega a[href="/le-club/brouillon"]', 'Drafts are hidden');
        self::assertSelectorTextContains('.c-mega__state', 'Visiteur');
        self::assertSelectorTextContains('.c-footer__title', 'Colonne');
        self::assertSelectorExists('.c-footer__links a[href="/mentions-legales"]');

        $this->client->loginUser($this->user('membre@example.org', 'member'));
        $this->client->request('GET', '/');
        self::assertSame(['Le Club', 'Actualités', 'Outils', 'Rubrique sans lien'], $this->headerLinks());

        self::assertSelectorTextContains('.c-mega__state', 'Connecté · Membre');

        $hiddenHeading = $this->link('Rubrique réservée');
        $hiddenHeading->setVisibility(Visibility::Groups)->addAllowedGroup($this->group('committee'));
        $this->link('Lien caché', $hiddenHeading, url: '/cache');
        $this->entityManager->flush();
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('.c-nav a[href="/cache"]', 'A hidden heading hides its links');
    }

    public function testOfficialDocumentsFollowTheirVisibility(): void
    {
        $category = new DocumentCategory('Textes fondateurs', 'textes-fondateurs');
        $this->entityManager->persist($category);
        $public = $this->document('Statuts', $category);
        $public->setVersion('V23')->setDetails(['Adoptés le 15/12/2025']);
        $private = $this->document('Compte rendu', $category);
        $private->setVisibility(Visibility::Groups)->addAllowedGroup($this->group('committee'));
        $this->entityManager->flush();

        // The file follows the document
        self::assertSame(Visibility::Groups, $private->getFile()->getVisibility());
        self::assertTrue($private->getFile()->getAllowedGroups()->contains($this->group('committee')));

        $this->page('Documents', 'documents', null, [
            new B\DocumentsBlock($category->getId(), 'Documents officiels', 'cards'),
            new B\DocumentBlock((int) $public->getId(), 'Consulter les statuts signés'),
            new B\DocumentBlock((int) $private->getId()),
        ]);

        $this->client->request('GET', '/documents');
        self::assertSelectorTextContains('.c-documents', 'Statuts');
        self::assertSelectorExists('.c-documents[data-layout="cards"]');
        self::assertSelectorTextContains('.c-document__facts', 'Version : V23');
        self::assertSelectorTextContains('.c-document__facts', 'Adoptés le 15/12/2025');
        self::assertSelectorTextContains('.c-document-card', 'Consulter les statuts signés');
        self::assertSelectorCount(1, '.c-document-card', 'A highlighted document the reader may not see is left out');
        self::assertSelectorTextNotContains('.c-documents', 'Compte rendu');
        $this->client->request('GET', '/media/'.$private->getFile()->getId().'/compte-rendu.pdf');
        self::assertResponseStatusCodeSame(302);

        $this->client->loginUser($this->user('comite@example.org', 'committee'));
        $this->client->request('GET', '/documents');
        self::assertSelectorTextContains('.c-documents', 'Compte rendu');
        self::assertSelectorExists('a[href^="/media/'.$public->getFile()->getId().'/"][download]');

        // Making it public again opens the file too (reloaded: the requests reset the entity manager)
        $private = $this->entityManager->find(Document::class, $private->getId());
        self::assertNotNull($private);
        $private->setVisibility(Visibility::Public)->removeAllowedGroup($this->group('committee'));
        $this->entityManager->flush();
        self::assertSame(Visibility::Public, $private->getFile()->getVisibility());
        self::assertCount(0, $private->getFile()->getAllowedGroups());
    }

    /**
     * @return list<string>
     */
    private function headerLinks(): array
    {
        return $this->client->getCrawler()->filter('.c-nav__link')->each(static fn ($link): string => trim(preg_replace('/\s*\(réservé\)/', '', $link->text()) ?? ''));
    }

    /**
     * @param list<B\BlockInterface> $body
     */
    private function page(string $title, string $slug, ?Page $parent = null, array $body = [], bool $published = true): Page
    {
        $page = (new Page($title, $slug))
            ->setParent($parent)
            ->setPublishedAt($published ? new \DateTimeImmutable('-1 day') : null)
            ->setBody(static::getContainer()->get(B\BlockFactory::class)->serializeAll($body));
        $parent?->getChildren()->add($page);
        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }

    private function link(string $label, ?MenuItem $parent = null, ?Page $page = null, ?string $url = null, MenuLocation $location = MenuLocation::Main): MenuItem
    {
        static $position = 0;
        $item = (new MenuItem($location, $label))->setParent($parent)->setPage($page)->setUrl($url)->setPosition($position++);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return $item;
    }

    private function document(string $title, DocumentCategory $category): Document
    {
        $file = static::getContainer()->get(MediaStorage::class)->storeCopy(\dirname(__DIR__, 2).'/fixtures/media/statuts-club-eplaneur.pdf');
        $this->entityManager->persist($file);
        $document = (new Document($title, $file))->setCategory($category);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    private function user(string $email, string $groupCode): User
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
