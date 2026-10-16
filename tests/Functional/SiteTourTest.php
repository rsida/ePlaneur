<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Functional\Admin\AdminUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The presentation of the site is for the committee only, linked from the main menu.
 */
final class SiteTourTest extends WebTestCase
{
    use AdminUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testOnlyTheCommitteeReadsThePresentation(): void
    {
        $this->client->request('GET', '/comite/le-site');
        self::assertResponseRedirects('/connexion');

        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', '/comite/le-site');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('a[href="/comite/le-site"]', 'The menu link is hidden from members');

        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $this->client->request('GET', '/');
        self::assertSelectorExists('a[href="/comite/le-site"]', 'The committee finds it in the main menu');
        $this->client->request('GET', '/comite/le-site');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Le nouveau site du club');
        self::assertSelectorCount(6, '.p-tour-wow');
        self::assertSelectorExists('a[href="/vols/ascendances"]');
        self::assertSelectorCount(7, '.p-tour-demo .p-tour-demo__link', 'Every step of the demo opens its example');
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
    }
}
