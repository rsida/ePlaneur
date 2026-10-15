<?php

declare(strict_types=1);

namespace App\Push;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Security\UserNotification;
use App\Session\CondorClubTasks;
use Doctrine\ORM\EntityManagerInterface;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Messenger\MessageBusInterface;
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
        private readonly PushSubscriptionRepository $subscriptions,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly MessageBusInterface $bus,
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
     * Sends the message to the devices of these accounts (null: every account) that want this kind of
     * notification, of verified accounts only.
     *
     * @param list<User>|null $users
     *
     * @return int the devices reached
     */
    public function notify(?array $users, UserNotification $kind, PushMessage $message): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        return $this->send($this->devices($users, $kind), $message);
    }

    /**
     * Sends the notification later, through the messenger worker: for a page that must not wait.
     *
     * @param list<User> $users
     */
    public function later(array $users, UserNotification $kind, PushMessage $message): void
    {
        $ids = array_values(array_filter(array_map(static fn (User $user): ?int => $user->wantsPush($kind) ? $user->getId() : null, $users)));
        if ($this->isEnabled() && [] !== $ids) {
            $this->bus->dispatch(new PushNotification($ids, $kind, $message));
        }
    }

    /**
     * The devices of these accounts (null: every account) that want this kind of notification.
     *
     * @param list<User>|null $users
     *
     * @return list<PushSubscription>
     */
    public function devices(?array $users, UserNotification $kind): array
    {
        return array_values(array_filter($this->subscriptions->findOfUsers($users), static fn (PushSubscription $device): bool => $device->getUser()->isVerified() && $device->getUser()->wantsPush($kind)));
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
