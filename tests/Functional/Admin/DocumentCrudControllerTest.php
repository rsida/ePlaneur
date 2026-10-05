<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\DocumentCrudController;
use App\Entity\Document;
use App\Media\MediaStorage;
use App\Security\Visibility;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * @extends AbstractCrudTestCase<DocumentCrudController>
 */
final class DocumentCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return DocumentCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    public function testADocumentIsCreatedFromALibraryFileAndRestrictsIt(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $media = static::getContainer()->get(MediaStorage::class)->storeCopy(\dirname(__DIR__, 3).'/fixtures/media/compte-rendu-cd-2026-09.pdf');
        $this->entityManager->persist($media);
        $this->entityManager->flush();
        $committee = $this->defaultGroup('committee');

        $crawler = $this->client->request('GET', $this->generateNewFormUrl());
        $form = $crawler->filter('form[name="Document"]')->form();
        $values = $form->getPhpValues();
        $values['Document']['title'] = 'Compte rendu de septembre';
        $values['Document']['file'] = (string) $media->getId();
        $values['Document']['version'] = 'V1';
        $values['Document']['details'] = ['Approuvé le 29/09/2026'];
        $values['Document']['visibility'] = Visibility::Groups->value;
        $values['Document']['allowedGroups'] = [(string) $committee->getId()];
        $this->client->request($form->getMethod(), $form->getUri(), $values);
        self::assertResponseRedirects();

        $document = $this->entityManager->getRepository(Document::class)->findOneBy(['title' => 'Compte rendu de septembre']);
        self::assertNotNull($document);
        self::assertSame(['Approuvé le 29/09/2026'], $document->getDetails());
        self::assertSame(Visibility::Groups, $document->getFile()?->getVisibility(), 'The file follows the document');
    }

    public function testADocumentNeedsAFile(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));

        $crawler = $this->client->request('GET', $this->generateNewFormUrl());
        $this->client->submit($crawler->filter('form[name="Document"]')->form(['Document[title]' => 'Sans fichier']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Choisissez le fichier du document.');
    }
}
