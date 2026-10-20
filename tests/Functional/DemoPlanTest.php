<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Functional\Admin\AdminUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The script of the demonstration is for the administrators only, linked from the back-office and from
 * the presentation of the site.
 */
final class DemoPlanTest extends WebTestCase
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

    public function testOnlyTheAdministratorsReadTheDemoPlan(): void
    {
        $this->client->request('GET', '/plan-de-demo');
        self::assertResponseRedirects('/connexion');

        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $this->client->request('GET', '/plan-de-demo');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/comite/le-site');
        self::assertSelectorNotExists('a[href="/plan-de-demo"]', 'The committee does not see the link');
        $this->client->request('GET', '/admin');
        self::assertSelectorNotExists('a[href="/plan-de-demo"]');

        $this->client->loginUser($this->userIn($this->entityManager, 'admin@example.org', 'admin'));
        $this->client->request('GET', '/admin');
        self::assertSelectorExists('a[href="/plan-de-demo"]', 'The back-office links to it');
        $this->client->request('GET', '/comite/le-site');
        self::assertSelectorExists('a[href="/plan-de-demo"]', 'The presentation of the site links to it');

        $this->client->request('GET', '/plan-de-demo');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Plan de la démonstration');
        self::assertSelectorCount(9, '.p-demo-act');
        self::assertSelectorCount(9, '.p-demo-toc a[href^="#"]:not([href="#questions"])', 'The contents lead to every act');
        $steps = $this->client->getCrawler()->filter('.p-demo-step');
        self::assertGreaterThan(40, $steps->count());
        self::assertSame($steps->count(), $this->client->getCrawler()->filter('.p-demo-step .c-button[href]')->count(), 'Every step opens its page');
        self::assertSame($steps->count(), $this->client->getCrawler()->filter('.p-demo-step .c-button[target="_blank"][rel="noopener"]')->count(), 'In a new tab, so the plan keeps its place');
        self::assertSelectorNotExists('.p-demo-toc a[target]', 'The contents stay in the plan');
        self::assertSame($this->client->getCrawler()->filter('.p-demo-step li')->count(), $this->client->getCrawler()->filter('.p-demo-step li .p-demo-step__why')->count(), 'Every point says what it is for');
        self::assertSelectorTextContains('#vol', 'ne cherche pas à remplacer Condor Club');
        self::assertSelectorExists('meta[name="robots"][content="noindex, nofollow"]');
    }
}
