<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Membership;
use App\Entity\MembershipSeason;
use App\Entity\MembershipType;
use App\Entity\User;
use App\Membership\MembershipStatus;
use App\Membership\Payment\HelloAssoClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Online payment of a membership with HelloAsso Checkout, against a fake HelloAsso API: the request
 * sends the member to HelloAsso, the return page and the notification record the payment only once
 * HelloAsso says it is done.
 */
final class MembershipPaymentTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    /** @var list<array{method: string, url: string, body: string}> */
    private array $calls = [];
    private bool $paid = false;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $http = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->calls[] = ['method' => $method, 'url' => $url, 'body' => (string) ($options['body'] ?? '')];

            return match (true) {
                str_ends_with($url, '/oauth2/token') => new MockResponse(json_encode(['access_token' => 'token', 'expires_in' => 1800], \JSON_THROW_ON_ERROR)),
                'POST' === $method && str_ends_with($url, '/v5/organizations/club-eplaneur/checkout-intents') => new MockResponse(json_encode(['id' => 123, 'redirectUrl' => 'https://checkout.helloasso.test/123'], \JSON_THROW_ON_ERROR)),
                str_ends_with($url, '/checkout-intents/123') => new MockResponse(json_encode($this->paid
                    ? ['id' => 123, 'order' => ['id' => 456, 'payments' => [['id' => 789, 'state' => 'Authorized']]]]
                    : ['id' => 123], \JSON_THROW_ON_ERROR)),
                default => new MockResponse('', ['http_code' => 404]),
            };
        });
        static::getContainer()->set(HelloAssoClient::class, new HelloAssoClient($http, new ArrayAdapter(), 'https://api.helloasso.test', 'club-eplaneur', 'client', 'secret'));

        $season = (new MembershipSeason(2026))->setOpen(true);
        $season->addType((new MembershipType())->setName('Adulte')->setPrice(2500));
        $this->entityManager->persist($season);
        $this->entityManager->flush();
    }

    public function testTheMemberPaysOnHelloAsso(): void
    {
        $this->client->loginUser($this->user('jeanne@example.org'));
        $this->request();

        self::assertResponseRedirects('https://checkout.helloasso.test/123');
        $membership = $this->membership();
        self::assertSame('123', $membership->getCheckoutIntentId());
        $checkout = json_decode(end($this->calls)['body'], true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(3000, $checkout['totalAmount']);
        self::assertTrue($checkout['containsDonation']);
        self::assertSame($membership->getId(), $checkout['metadata']['membership']);
        self::assertSame('FRA', $checkout['payer']['country']);
        self::assertStringEndsWith('/adhesion/paiement/retour', $checkout['returnUrl']);

        // Back before HelloAsso confirmed it: nothing recorded
        $this->client->request('GET', '/adhesion/paiement/retour?checkoutIntentId=123&code=succeeded');
        self::assertSame(MembershipStatus::Requested, $this->membership()->getStatus());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.p-membership__mine', 'Payer 30');

        $this->paid = true;
        $this->client->request('GET', '/adhesion/paiement/retour?checkoutIntentId=123&code=succeeded');
        $membership = $this->membership();
        self::assertSame(MembershipStatus::Paid, $membership->getStatus());
        self::assertSame(Membership::PAYMENT_ONLINE, $membership->getPaymentMethod());
        self::assertSame('HelloAsso 456', $membership->getPaymentReference());
    }

    public function testTheNotificationIsCheckedWithHelloAsso(): void
    {
        $this->client->loginUser($this->user('jeanne@example.org'));
        $this->request();
        $id = $this->membership()->getId();
        $this->client->restart();

        // A forged notification does not record anything while HelloAsso has no order
        $this->client->request('POST', '/adhesion/paiement/notification', content: json_encode(['eventType' => 'Order', 'metadata' => ['membership' => $id]], \JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(204);
        self::assertSame(MembershipStatus::Requested, $this->membership()->getStatus());

        $this->paid = true;
        $this->client->request('POST', '/adhesion/paiement/notification', content: json_encode(['eventType' => 'Order', 'metadata' => ['membership' => $id]], \JSON_THROW_ON_ERROR));
        self::assertSame(MembershipStatus::Paid, $this->membership()->getStatus());
    }

    public function testTheReturnPageIsForThePayerOnly(): void
    {
        $this->client->loginUser($this->user('jeanne@example.org'));
        $this->request();

        $this->client->loginUser($this->user('autre@example.org'));
        $this->client->request('GET', '/adhesion/paiement/retour?checkoutIntentId=123');
        self::assertResponseStatusCodeSame(404);
    }

    private function request(): void
    {
        $crawler = $this->client->request('GET', '/adhesion');
        self::assertSelectorTextContains('form[name="membership_request"] button', 'payer en ligne');
        $form = $crawler->filter('form[name="membership_request"]')->form([
            'membership_request[firstName]' => 'Jeanne',
            'membership_request[lastName]' => 'Pilote',
            'membership_request[birthDate]' => '1990-04-01',
            'membership_request[address]' => '12 rue des Thermiques',
            'membership_request[postalCode]' => '97400',
            'membership_request[city]' => 'Saint-Denis',
            'membership_request[country]' => 'FR',
            'membership_request[phone]' => '0692',
            'membership_request[donation]' => '5',
            'membership_request[rulesAccepted]' => '1',
        ]);
        $type = $form['membership_request[type]'];
        \assert($type instanceof ChoiceFormField);
        $type->select((string) $type->availableOptionValues()[0]);
        $this->client->submit($form);
    }

    private function membership(): Membership
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository(Membership::class)->findOneBy([]) ?? throw new \LogicException('No membership');
    }

    private function user(string $email): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($email)->setVerified(true)->setPassword('x');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
