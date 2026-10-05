<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\MediaCrudController;
use App\Entity\Document;
use App\Entity\Media;
use App\Media\MediaStorage;
use App\Security\Visibility;
use EasyCorp\Bundle\EasyAdminBundle\Test\AbstractCrudTestCase;

/**
 * @extends AbstractCrudTestCase<MediaCrudController>
 */
final class MediaCrudControllerTest extends AbstractCrudTestCase
{
    use AdminUsers;

    protected function getControllerFqcn(): string
    {
        return MediaCrudController::class;
    }

    protected function getDashboardFqcn(): string
    {
        return DashboardController::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
    }

    public function testFilesAreUploadedWithTheirVisibility(): void
    {
        $crawler = $this->client->request('GET', '/admin/media/upload');
        $form = $crawler->filter('form[name="media_upload"]')->form();
        $form['media_upload[files]'][0]->upload($this->temporaryCopy('statuts-club-eplaneur.pdf'));
        $form->setValues(['media_upload[visibility]' => Visibility::Authenticated->value]);
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/media');
        $media = $this->entityManager->getRepository(Media::class)->findOneBy(['originalName' => 'statuts-club-eplaneur.pdf'], ['id' => 'DESC']);
        self::assertNotNull($media);
        self::assertSame('application/pdf', $media->getMimeType());
        self::assertSame(Visibility::Authenticated, $media->getVisibility());
        self::assertFileExists(static::getContainer()->get(MediaStorage::class)->pathOf($media));

        $this->client->request('GET', $this->generateIndexUrl());
        self::assertSelectorTextContains('body', 'statuts-club-eplaneur.pdf');
    }

    public function testRefusedFileTypesAreNotStored(): void
    {
        $crawler = $this->client->request('GET', '/admin/media/upload');
        $form = $crawler->filter('form[name="media_upload"]')->form();
        $path = tempnam(sys_get_temp_dir(), 'media').'.html';
        file_put_contents($path, '<html><script>alert(1)</script></html>');
        $form['media_upload[files]'][0]->upload($path);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'n’est pas accepté');
    }

    public function testAFileUsedByADocumentCannotBeDeleted(): void
    {
        $storage = static::getContainer()->get(MediaStorage::class);
        $media = $storage->storeCopy(\dirname(__DIR__, 3).'/fixtures/media/statuts-club-eplaneur.pdf');
        $this->entityManager->persist($media);
        $this->entityManager->persist(new Document('Statuts', $media));
        $this->entityManager->flush();
        $id = $media->getId();

        $crawler = $this->client->request('GET', $this->generateIndexUrl());
        $token = (string) $crawler->filter('#action-confirmation-form input[name="token"]')->attr('value');
        $this->client->request('POST', '/admin/media/'.$id.'/delete', ['token' => $token]);

        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(Media::class, $id));
        self::assertFileExists($storage->pathOf($media));
    }

    /** Path of a copy of a fixture file, named like the original (the upload moves it). */
    private function temporaryCopy(string $fixture): string
    {
        $directory = sys_get_temp_dir().'/'.bin2hex(random_bytes(4));
        mkdir($directory);
        copy(\dirname(__DIR__, 3).'/fixtures/media/'.$fixture, $directory.'/'.$fixture);

        return $directory.'/'.$fixture;
    }
}
