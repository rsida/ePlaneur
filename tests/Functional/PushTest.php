<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Entity\FlightComment;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\FlightMailer;
use App\Flight\Track;
use App\Flight\Turnpoint;
use App\Messaging\Conversations;
use App\Push\PushNotification;
use App\Push\PushNotificationHandler;
use App\Security\UserNotification;
use App\Tests\Functional\Admin\AdminUsers;
use App\Tests\Unit\Flight\FlightSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Notifications of the installable site: a device of the member turns them on, tries them and turns
 * them off; nobody else can touch it; the member chooses what their devices receive; a new message
 * and a comment on a flight are sent later, by the worker.
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

    public function testTheMemberChoosesWhatTheirDevicesReceive(): void
    {
        $lea = $this->userIn($this->entityManager, 'lea@example.org');
        $this->client->loginUser($lea);
        $crawler = $this->client->request('GET', '/mon-compte/profil');
        $form = $crawler->selectButton('Enregistrer mes notifications')->form();
        /** @var list<ChoiceFormField> $boxes */
        $boxes = $form['push[pushNotifications]'];
        self::assertSame(['sessions', 'message', 'flight_comment'], array_map(static fn (ChoiceFormField $box): string => $box->availableOptionValues()[0], $boxes));
        self::assertTrue($boxes[0]->hasValue(), 'Everything by default');
        $boxes[0]->untick();
        $boxes[2]->untick();

        $this->client->submit($form);
        self::assertResponseRedirects('/mon-compte/profil#push');
        $this->entityManager->clear();
        $lea = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'lea@example.org']);
        self::assertSame([UserNotification::Message], $lea?->getPushNotifications());
    }

    public function testANewMessageAndACommentReachTheDevicesThatWantThem(): void
    {
        $lea = $this->userIn($this->entityManager, 'lea@example.org');
        $max = $this->userIn($this->entityManager, 'max@example.org');
        $max->setPushNotifications([UserNotification::FlightComment]);
        $this->device($lea, self::PUSH_SERVICE.'lea-phone');
        $this->device($max, self::PUSH_SERVICE.'max-phone');
        $container = static::getContainer();

        $container->get(Conversations::class)->start($max, $lea, 'Vol de mercredi', 'On se retrouve sur Discord ?');
        $container->get(Conversations::class)->start($lea, $max, 'Merci', 'Merci pour le vol !');
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $track = new Track('igc', 'Condor 2', 'Max', null, 'ASK21', 'AA2', null, 'C2-d015 ePlaneur', [$point('Terrain', 0, 3000), $point('Départ', 0, 500), $point('Arrivée', 7000, 500)], FlightSamples::glideThermalGlide(), true, new \DateTimeImmutable('2026-10-14'));
        $flight = new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', $max, new \DateTimeImmutable('2026-10-14'));
        $comment = (new FlightComment($flight, $lea, new \DateTimeImmutable()))->setBody('Belle spirale !');
        $this->entityManager->persist($flight);
        $this->entityManager->persist($comment);
        $this->entityManager->flush();
        $container->get(FlightMailer::class)->sendComment($comment);

        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        $queued = array_values(array_filter(array_map(static fn ($envelope): object => $envelope->getMessage(), $transport->getSent()), static fn (object $message): bool => $message instanceof PushNotification));
        self::assertCount(2, $queued, 'Léa gets the message; Max wants only the comments');
        self::assertSame('Message de Test max@example.org', $queued[0]->message->title);
        self::assertSame('Test lea@example.org a commenté votre vol', $queued[1]->message->title, 'Comment of Léa');
        self::assertSame([], $this->pushed, 'Sent later, by the worker');

        foreach ($queued as $notification) {
            $container->get(PushNotificationHandler::class)($notification);
        }
        self::assertSame([self::PUSH_SERVICE.'lea-phone', self::PUSH_SERVICE.'max-phone'], array_column($this->pushed, 'url'));
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
