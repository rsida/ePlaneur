<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Media;
use App\Media\ImageVariants;
use App\Media\MediaStorage;
use App\Security\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Resized pictures: made on first request, smaller than the original, with its access rules.
 */
final class MediaVariantTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAPictureIsResizedToWebp(): void
    {
        $media = $this->storeMedia('cover-glider-alps.png');
        self::assertGreaterThan(400, $media->getWidth());

        $this->client->request('GET', '/media/'.$media->getId().'/thumb/cover-glider-alps.webp');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
        self::assertStringContainsString('immutable', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $variant = static::getContainer()->get(ImageVariants::class)->path($media, 'thumb');
        self::assertSame([400, 400], \array_slice((array) getimagesize($variant), 0, 2));
        self::assertLessThan($media->getSize(), filesize($variant));

        static::getContainer()->get(MediaStorage::class)->delete($media);
        self::assertFileDoesNotExist($variant, 'Deleting a media deletes its versions');
    }

    public function testARestrictedPictureKeepsItsAccessRules(): void
    {
        $media = $this->storeMedia('cover-glider-alps.png')->setVisibility(Visibility::Authenticated);
        $this->entityManager->flush();

        $this->client->request('GET', '/media/'.$media->getId().'/content/image.webp');

        self::assertResponseRedirects('/connexion');
    }

    public function testTheTwigHelperGivesTheVariantAddress(): void
    {
        $media = $this->storeMedia('cover-glider-alps.png');
        $twig = static::getContainer()->get('twig');

        $url = $twig->createTemplate("{{ image_url(media, 'card') }}")->render(['media' => $media]);

        self::assertSame('/media/'.$media->getId().'/card/cover-glider-alps.webp', $url);
    }

    private function storeMedia(string $fixture): Media
    {
        $media = static::getContainer()->get(MediaStorage::class)->storeCopy(\dirname(__DIR__, 2).'/fixtures/media/'.$fixture);
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }
}
