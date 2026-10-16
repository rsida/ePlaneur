<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomePageIsAvailable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Prenez le large');
        self::assertSelectorExists('header nav a[aria-current="page"]');
        self::assertSelectorNotExists('main a[href="#"]', 'Every link of the home page leads somewhere');
    }

    public function testLoggedInReaderUploadsATrackFromTheHomePage(): void
    {
        $client = static::createClient();
        $user = (new User())->setEmail('lecteur@example.org')->setDisplayName('Lecteur')->setVerified(true)->setPassword('x');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();
        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertSelectorExists('#carnet form[action="/vols"][enctype="multipart/form-data"] input[type="file"][name="trace"]');
        self::assertSelectorExists('#carnet form input[name="_token"]');
    }
}
