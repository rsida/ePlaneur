<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\Group;
use App\Entity\Media;
use App\Entity\User;
use App\Media\MediaStorage;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Media window of the block editor: listing, upload and alt text, and who may change the library.
 */
final class EditorDataControllerTest extends WebTestCase
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

    public function testTheLibraryIsSearchedAndFilteredByType(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $image = $this->storeMedia('duo-airfield.png')->setAlt('Deux planeurs sur la piste');
        $this->storeMedia('compte-rendu-cd-2026-09.pdf');
        $this->entityManager->flush();

        $result = $this->getJson('/admin/editor/media?type=image&q=planeurs');
        self::assertSame(1, $result['total']);
        self::assertSame($image->getId(), $result['items'][0]['id']);
        self::assertSame('image', $result['items'][0]['type']);
        self::assertSame('Deux planeurs sur la piste', $result['items'][0]['alt']);
        self::assertNotNull($result['items'][0]['url']);

        $pdf = $this->getJson('/admin/editor/media?type=pdf');
        self::assertSame(['pdf'], array_unique(array_column($pdf['items'], 'type')));

        $one = $this->getJson('/admin/editor/media/'.$image->getId());
        self::assertSame('duo-airfield.png', $one['name']);
    }

    public function testFilesAreUploadedAndDescribedFromTheEditor(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $token = $this->editorToken();
        $copy = sys_get_temp_dir().'/'.uniqid('upload', true).'.png';
        copy($this->fixture('duo-airfield.png'), $copy);

        $this->client->request('POST', '/admin/editor/media/upload', [], ['files' => [new UploadedFile($copy, 'piste.png', 'image/png', null, true)]], ['HTTP_X-CSRF-Token' => $token]);
        self::assertResponseIsSuccessful();
        /** @var array{items: list<array{id: int}>} $result */
        $result = json_decode((string) $this->client->getResponse()->getContent(), true);
        $media = $this->entityManager->getRepository(Media::class)->find($result['items'][0]['id']);
        self::assertSame('piste.png', $media?->getOriginalName());

        $this->client->request('POST', '/admin/editor/media/'.$media->getId(), ['alt' => 'Une piste', 'credit' => ''], [], ['HTTP_X-CSRF-Token' => $token]);
        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $media = $this->entityManager->getRepository(Media::class)->find($media->getId());
        self::assertSame('Une piste', $media?->getAlt());
        self::assertNull($media->getCredit());

        $this->client->request('POST', '/admin/editor/media/'.$media->getId(), ['alt' => 'Sans jeton'], [], ['HTTP_X-CSRF-Token' => 'wrong']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnacceptedFilesAreRefused(): void
    {
        $this->client->loginUser($this->userIn($this->entityManager, 'comite@example.org', 'committee'));
        $copy = sys_get_temp_dir().'/'.uniqid('upload', true).'.html';
        file_put_contents($copy, '<html><body>page</body></html>');

        $this->client->request('POST', '/admin/editor/media/upload', [], ['files' => [new UploadedFile($copy, 'page.html', 'text/html', null, true)]], ['HTTP_X-CSRF-Token' => $this->editorToken()]);

        self::assertResponseStatusCodeSame(422);
        $result = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(['page.html : Ce type de fichier n’est pas accepté.'], $result['errors']);
    }

    public function testWritersWithoutMediaManageOnlyChoose(): void
    {
        $writers = (new Group('writers', 'Rédaction'))->setPermissions([Permission::AdminAccess, Permission::PostCreate]);
        $this->entityManager->persist($writers);
        $writer = (new User())->setEmail('auteur@example.org')->setDisplayName('Auteur')->setVerified(true)->setPassword('x')->addGroup($writers);
        $this->entityManager->persist($writer);
        $image = $this->storeMedia('duo-airfield.png');
        $this->client->loginUser($writer);

        $crawler = $this->client->request('GET', '/admin/post/new');
        self::assertCount(0, $crawler->filter('[data-media-tab="upload"]'), 'No upload tab without MEDIA_MANAGE');
        self::assertSame('false', $crawler->filter('.ep-media-dialog')->attr('data-can-edit'));
        $this->getJson('/admin/editor/media');

        $this->client->request('POST', '/admin/editor/media/'.$image->getId(), ['alt' => 'Non'], [], ['HTTP_X-CSRF-Token' => (string) $crawler->filter('.ep-media-dialog')->attr('data-token')]);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url): array
    {
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    private function editorToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/post/new');

        return (string) $crawler->filter('.ep-media-dialog')->attr('data-token');
    }

    private function storeMedia(string $fixture): Media
    {
        $media = static::getContainer()->get(MediaStorage::class)->storeCopy($this->fixture($fixture));
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    private function fixture(string $name): string
    {
        return \dirname(__DIR__, 3).'/fixtures/media/'.$name;
    }
}
