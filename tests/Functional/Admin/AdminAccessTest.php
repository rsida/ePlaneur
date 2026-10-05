<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Who may open the back-office and which screens: ADMIN_ACCESS for the dashboard, then one
 * permission per screen.
 */
final class AdminAccessTest extends WebTestCase
{
    use AdminUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testVisitorsAreSentToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/connexion');
    }

    public function testAccountsWithoutAdminAccessAreRefused(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'inscrit@example.org'));
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/mon-compte');
        self::assertSelectorNotExists('a[href="/admin"]');
    }

    public function testTheCommitteeOnlySeesTheScreensOfItsPermissions(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));

        $this->client->request('GET', '/mon-compte');
        self::assertSelectorExists('a[href="/admin"]', 'The account page links to the back-office');

        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $shortcuts = $crawler->filter('.ep-shortcut__title')->each(static fn ($node): string => $node->text());
        self::assertContains('Médiathèque', $shortcuts);
        self::assertContains('Documents officiels', $shortcuts);
        self::assertNotContains('Comptes', $shortcuts, 'USER_MANAGE is not granted to the committee');
        self::assertNotContains('Menus', $shortcuts, 'MENU_MANAGE is not granted to the committee');

        $this->client->request('GET', '/admin/media');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/user');
        self::assertResponseStatusCodeSame(403, 'A hidden screen is refused by URL too');
        $this->client->request('GET', '/admin/group');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdministratorsSeeEveryScreen(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'admin@example.org', 'admin'));

        foreach (['/admin', '/admin/user', '/admin/group', '/admin/category', '/admin/media', '/admin/media/upload', '/admin/document', '/admin/document/new', '/admin/document-category', '/admin/menu-item', '/admin/menu-item/new'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }
}
