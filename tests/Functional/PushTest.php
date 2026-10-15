<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\PushSubscription;
use App\Tests\Functional\Admin\AdminUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Notifications of the installable site: a device of the member turns them on, tries them and turns
 * them off; nobody else can touch it.
 */
final class PushTest extends WebTestCase
{
    use AdminUsers;
    use PushDevicesTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->mockPushService();
    }

    public function testADeviceTurnsTheNotificationsOnTriesThemAndTurnsThemOff(): void
    {
        $lea = $this->userIn($this->entityManager, 'lea@example.org');
        $this->client->loginUser($lea);
        $token = $this->profileToken();
        $endpoint = self::PUSH_SERVICE.'lea-phone';
        $keys = self::browserKeys();
        $subscription = ['endpoint' => $endpoint, 'expirationTime' => null, 'keys' => ['p256dh' => $keys['publicKey'], 'auth' => $keys['authToken']]];

        $this->post('/notifications/appareils', $subscription, 'wrong');
        self::assertResponseStatusCodeSame(403);
        $this->post('/notifications/appareils', ['endpoint' => 'http://localhost/push', 'keys' => $subscription['keys']], $token);
        self::assertResponseStatusCodeSame(422, 'Only https addresses');
        $this->post('/notifications/appareils', $subscription, $token);
        self::assertResponseStatusCodeSame(204);
        $this->post('/notifications/appareils', $subscription, $token);
        self::assertCount(1, $this->devices(), 'Subscribing again keeps one device');

        $this->post('/notifications/appareils/essai', ['endpoint' => $endpoint], $token);
        self::assertResponseStatusCodeSame(204);
        self::assertCount(1, $this->pushed);
        self::assertSame($endpoint, $this->pushed[0]['url']);
        self::assertContains('Content-Encoding: aes128gcm', $this->pushed[0]['headers']);
        self::assertNotEmpty(array_filter($this->pushed[0]['headers'], static fn (string $header): bool => str_starts_with($header, 'Authorization: vapid t=')));

        $this->post('/notifications/appareils/retrait', ['endpoint' => $endpoint], $token);
        self::assertResponseStatusCodeSame(204);
        self::assertSame([], $this->devices());
    }

    public function testNobodyElseTouchesTheDevice(): void
    {
        $lea = $this->userIn($this->entityManager, 'lea@example.org');
        $this->device($lea, self::PUSH_SERVICE.'lea-phone');
        $this->client->loginUser($this->userIn($this->entityManager, 'max@example.org'));
        $token = $this->profileToken();

        $this->post('/notifications/appareils/essai', ['endpoint' => self::PUSH_SERVICE.'lea-phone'], $token);
        self::assertResponseStatusCodeSame(404);
        $this->post('/notifications/appareils/retrait', ['endpoint' => self::PUSH_SERVICE.'lea-phone'], $token);
        self::assertCount(1, $this->devices());
        self::assertSame([], $this->pushed);
    }

    public function testAVisitorCannotSubscribe(): void
    {
        $this->client->jsonRequest('POST', '/notifications/appareils', ['endpoint' => self::PUSH_SERVICE.'x']);
        self::assertResponseRedirects();
    }

    public function testADeviceWhoseSubscriptionIsOverIsForgotten(): void
    {
        $lea = $this->userIn($this->entityManager, 'lea@example.org');
        $this->device($lea, self::PUSH_SERVICE.'expired');
        $this->client->loginUser($lea);

        $this->post('/notifications/appareils/essai', ['endpoint' => self::PUSH_SERVICE.'expired'], $this->profileToken());
        self::assertResponseStatusCodeSame(502);
        self::assertSame([], $this->devices());
    }

    private function profileToken(): string
    {
        $crawler = $this->client->request('GET', '/mon-compte/profil');
        self::assertSelectorTextContains('#push-title', 'Notifications sur cet appareil');

        return (string) $crawler->filter('[data-controller="push"]')->attr('data-push-token-value');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $url, array $body, string $token): void
    {
        $this->client->jsonRequest('POST', $url, $body, ['HTTP_X_CSRF_TOKEN' => $token]);
    }

    /** @return list<PushSubscription> */
    private function devices(): array
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(PushSubscription::class)->findAll();
    }
}
