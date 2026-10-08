<?php

declare(strict_types=1);

namespace App\Membership\Payment;

use App\Entity\Membership;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HelloAsso Checkout (API v5) for the online payment of memberships (roadmap step 8b): the member
 * pays on HelloAsso's page, the site never sees a card. Disabled while the club's API keys are not
 * set (HELLOASSO_* variables).
 *
 * - token: OAuth2 client credentials (`/oauth2/token`), kept in cache until it expires;
 * - checkout: `POST /v5/organizations/{organization}/checkout-intents` with the amounts in cents, the
 *   payer and the membership id in the metadata, answers the id and the page to redirect to;
 * - check: `GET /v5/organizations/{organization}/checkout-intents/{id}` has an `order` once paid. The
 *   site always checks there, never trusts the query of the return page or a notification.
 */
final readonly class HelloAssoClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire('%env(HELLOASSO_API_URL)%')]
        private string $apiUrl,
        #[Autowire('%env(HELLOASSO_ORGANIZATION)%')]
        private string $organization,
        #[Autowire('%env(HELLOASSO_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(HELLOASSO_CLIENT_SECRET)%')]
        private string $clientSecret,
    ) {
    }

    public function isEnabled(): bool
    {
        return '' !== $this->organization && '' !== $this->clientId && '' !== $this->clientSecret;
    }

    /**
     * Opens a payment on HelloAsso for a membership.
     *
     * @param array{return: string, back: string, error: string} $urls where HelloAsso sends the member back
     *
     * @return array{id: string, redirectUrl: string}
     */
    public function createCheckout(Membership $membership, array $urls): array
    {
        $payer = array_filter([
            'firstName' => $membership->getFirstName(),
            'lastName' => $membership->getLastName(),
            'email' => $membership->getUser()?->getEmail() ?? $membership->getEmail(),
            'dateOfBirth' => $membership->getBirthDate()?->format('Y-m-d'),
            'address' => $membership->getAddress(),
            'zipCode' => $membership->getPostalCode(),
            'city' => $membership->getCity(),
            'country' => self::country($membership->getCountry()),
        ]);
        $data = $this->request('POST', \sprintf('/v5/organizations/%s/checkout-intents', rawurlencode($this->organization)), ['json' => [
            'totalAmount' => $membership->getTotal(),
            'initialAmount' => $membership->getTotal(),
            'itemName' => mb_substr(\sprintf('Adhésion %d – %s', $membership->getSeason()->getYear(), $membership->getType()?->getName() ?? ''), 0, 250),
            'backUrl' => $urls['back'],
            'errorUrl' => $urls['error'],
            'returnUrl' => $urls['return'],
            'containsDonation' => $membership->getDonation() > 0,
            'payer' => $payer,
            'metadata' => ['membership' => $membership->getId()],
        ]]);

        return ['id' => (string) $data['id'], 'redirectUrl' => (string) $data['redirectUrl']];
    }

    /**
     * Id of the order once the payment is done, null while it is not.
     */
    public function paidOrder(string $checkoutIntentId): ?string
    {
        $data = $this->request('GET', \sprintf('/v5/organizations/%s/checkout-intents/%s', rawurlencode($this->organization), rawurlencode($checkoutIntentId)));
        $order = $data['order'] ?? null;
        if (!\is_array($order) || !isset($order['id'])) {
            return null;
        }
        foreach ($order['payments'] ?? [] as $payment) {
            if (\is_array($payment) && \in_array($payment['state'] ?? null, ['Authorized', 'Registered'], true)) {
                return (string) $order['id'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $response = $this->httpClient->request($method, rtrim($this->apiUrl, '/').$path, $options + ['auth_bearer' => $this->token()]);

        /* @var array<string, mixed> */
        return $response->toArray();
    }

    private function token(): string
    {
        return $this->cache->get('helloasso_token_'.md5($this->clientId), function (ItemInterface $item): string {
            $data = $this->httpClient->request('POST', rtrim($this->apiUrl, '/').'/oauth2/token', ['body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]])->toArray();
            $item->expiresAfter(max(60, (int) ($data['expires_in'] ?? 1800) - 60));

            return (string) $data['access_token'];
        });
    }

    /** HelloAsso wants the 3-letter country code. */
    private static function country(string $alpha2): ?string
    {
        return ['FR' => 'FRA', 'BE' => 'BEL', 'CH' => 'CHE', 'CA' => 'CAN', 'LU' => 'LUX', 'MC' => 'MCO', 'DE' => 'DEU', 'ES' => 'ESP', 'IT' => 'ITA', 'GB' => 'GBR', 'US' => 'USA'][$alpha2] ?? null;
    }
}
