<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Contact\ContactSubject;
use App\Controller\Admin\ContactRequestCrudController;
use App\Controller\Admin\DashboardController;
use App\Entity\ContactRequest;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * Messages of the contact form in the back-office: listed, counted in the menu, marked processed.
 *
 * @extends AbstractCrudTestCase<ContactRequestCrudController>
 */
final class ContactRequestCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return ContactRequestCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testTheCommitteeReadsAndProcessesTheMessages(): void
    {
        $this->client->disableReboot();
        $request = (new ContactRequest(new \DateTimeImmutable('2026-10-09 09:00')))
            ->setName('Paul Visiteur')->setEmail('paul@example.org')->setSubject(ContactSubject::Training)->setMessage('Comment commencer la formation ?');
        $this->entityManager->persist($request);
        $this->entityManager->flush();
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));

        $crawler = $this->client->request('GET', $this->generateIndexUrl());
        self::assertSelectorTextContains('tbody', 'Paul Visiteur');
        self::assertSelectorTextContains('body', 'Messages de contact');

        $this->client->request('GET', $this->generateDetailUrl((int) $request->getId()));
        self::assertSelectorTextContains('body', 'Comment commencer la formation ?');

        $this->client->submit($crawler->filter('form[action*="/traite"]')->form());
        $this->entityManager->clear();
        $processed = $this->entityManager->getRepository(ContactRequest::class)->find($request->getId());
        self::assertTrue($processed?->isProcessed());
        self::assertSame('comite@example.org', $processed->getProcessedBy()?->getEmail());
    }

    public function testMembersCannotReadThem(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'membre@example.org', 'member'));
        $this->client->request('GET', $this->generateIndexUrl());

        self::assertResponseStatusCodeSame(403);
    }
}
