<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Push\PushNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Devices that receive notifications, and a push service that records what it is sent: 201, or 410
 * for the addresses that end with "/expired". The service has a public IP address (PUSH_SERVICE):
 * a made-up host name would not resolve, and private addresses are refused.
 */
trait PushDevicesTrait
{
    private const string PUSH_SERVICE = 'https://93.184.215.14/send/';

    /** @var list<array{url: string, headers: list<string>}> */
    private array $pushed = [];

    private function mockPushService(): void
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->pushed[] = ['url' => $url, 'headers' => $options['headers'] ?? []];

            return new MockResponse('', ['http_code' => str_ends_with($url, '/expired') ? 410 : 201]);
        });
        $container = static::getContainer();
        $container->set(PushNotifier::class, new PushNotifier(
            $container->get(EntityManagerInterface::class),
            $http,
            new NullLogger(),
            (string) $_SERVER['VAPID_PUBLIC_KEY'],
            (string) $_SERVER['VAPID_PRIVATE_KEY'],
            'mailto:test@example.org',
        ));
    }

    /** A device with the keys a browser would give. */
    private function device(User $user, string $endpoint): PushSubscription
    {
        $device = new PushSubscription($user, $endpoint, ...self::browserKeys(), createdAt: new \DateTimeImmutable());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($device);
        $entityManager->flush();

        return $device;
    }

    /** @return array{publicKey: string, authToken: string} */
    private static function browserKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => \OPENSSL_KEYTYPE_EC]) ?: throw new \RuntimeException('No EC key');
        $ec = openssl_pkey_get_details($key)['ec'];
        $encode = static fn (string $bytes): string => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return ['publicKey' => $encode("\x04".str_pad($ec['x'], 32, "\0", \STR_PAD_LEFT).str_pad($ec['y'], 32, "\0", \STR_PAD_LEFT)), 'authToken' => $encode(random_bytes(16))];
    }
}
