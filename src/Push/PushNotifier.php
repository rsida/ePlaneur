<?php

declare(strict_types=1);

namespace App\Push;

use App\Entity\PushSubscription;
use App\Session\CondorClubTasks;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends notifications to the devices that turned them on (roadmap 11l, doc/installable-site.md#notifications),
 * signed with the VAPID keys of the site. A device whose browser says the subscription is over is
 * forgotten. Without keys, the notifications are off and nothing is offered.
 */
final class PushNotifier
{
    /** Seconds a push service keeps a notification for a device that is off. */
    private const int TTL = 86400;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(VAPID_PUBLIC_KEY)%')]
        private readonly string $publicKey,
        #[Autowire('%env(VAPID_PRIVATE_KEY)%')]
        private readonly string $privateKey,
        #[Autowire('mailto:%env(CONTACT_EMAIL)%')]
        private readonly string $subject,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->publicKey && '' !== $this->privateKey;
    }

    /** The key the browser needs to subscribe (applicationServerKey), base64url. */
    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Sends the message to these devices and forgets those whose subscription is over.
     *
     * @param list<PushSubscription> $devices
     *
     * @return int the devices reached
     */
    public function send(array $devices, PushMessage $message): int
    {
        if (!$this->isEnabled() || [] === $devices) {
            return 0;
        }
        // The addresses come from the browsers: never let one point to the network of the server
        $client = new Psr18Client(new NoPrivateNetworkHttpClient($this->httpClient->withOptions(['headers' => ['User-Agent' => CondorClubTasks::USER_AGENT]])));
        $webPush = new WebPush(['VAPID' => ['subject' => $this->subject, 'publicKey' => $this->publicKey, 'privateKey' => $this->privateKey]], ['TTL' => self::TTL], $client, $client, $client, logger: $this->logger);
        $payload = json_encode($message, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $byEndpoint = [];
        foreach ($devices as $device) {
            $byEndpoint[$device->getEndpoint()] = $device;
            $webPush->queueNotification(new Subscription($device->getEndpoint(), $device->getPublicKey(), $device->getAuthToken(), 'aes128gcm'), $payload);
        }
        $reached = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                ++$reached;
            } elseif ($report->isSubscriptionExpired() && isset($byEndpoint[$report->getEndpoint()])) {
                $this->entityManager->remove($byEndpoint[$report->getEndpoint()]);
            } else {
                $this->logger->warning('Push notification not delivered: {reason}', ['reason' => $report->getReason()]);
            }
        }
        $this->entityManager->flush();

        return $reached;
    }
}
