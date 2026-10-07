<?php

declare(strict_types=1);

namespace App\WordPress;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the WordPress site through its REST API (/wp-json/wp/v2). Anonymous requests only return
 * public content; with the user and application password of WORDPRESS_USER and
 * WORDPRESS_APP_PASSWORD, reserved content is returned too.
 *
 * The site answers its first requests slowly at times (no cache on the API): requests wait up to a
 * minute and are tried up to three times (timeouts, 5xx), one, two then four seconds apart.
 */
final readonly class WordPressClient
{
    private const int PER_PAGE = 100;

    /** Seconds without data before a request is given up (then retried) */
    private const int TIMEOUT = 60;

    private HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire('%env(WORDPRESS_URL)%')]
        private string $baseUrl,
        #[Autowire('%env(WORDPRESS_USER)%')]
        private string $user,
        #[Autowire('%env(WORDPRESS_APP_PASSWORD)%')]
        private string $password,
    ) {
        $this->httpClient = new RetryableHttpClient($httpClient, maxRetries: 3);
    }

    public function hasCredentials(): bool
    {
        return '' !== $this->user && '' !== $this->password;
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * Every item of a collection (posts, pages, categories), all pages of results.
     *
     * @param array<string, string|int> $query
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $collection, array $query = [], bool $authenticated = true): array
    {
        $items = [];
        for ($page = 1;; ++$page) {
            $response = $this->httpClient->request('GET', $this->baseUrl().'/wp-json/wp/v2/'.$collection, [
                'query' => [...$query, 'per_page' => self::PER_PAGE, 'page' => $page],
                'auth_basic' => $authenticated && $this->hasCredentials() ? [$this->user, $this->password] : null,
                'timeout' => self::TIMEOUT,
            ]);
            /** @var list<array<string, mixed>> $batch */
            $batch = $response->toArray();
            array_push($items, ...$batch);
            if ($page >= (int) ($response->getHeaders()['x-wp-totalpages'][0] ?? 1)) {
                return $items;
            }
        }
    }

    /**
     * One item (a media of a featured image...), or null when it does not exist.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $collection, int $id): ?array
    {
        $response = $this->httpClient->request('GET', $this->baseUrl().'/wp-json/wp/v2/'.$collection.'/'.$id, [
            'auth_basic' => $this->hasCredentials() ? [$this->user, $this->password] : null,
            'timeout' => self::TIMEOUT,
        ]);

        return 200 === $response->getStatusCode() ? $response->toArray() : null;
    }

    /**
     * Downloads a file of the site into a temporary file and returns its path.
     *
     * @throws \RuntimeException when the file cannot be downloaded
     */
    public function download(string $url): string
    {
        $response = $this->httpClient->request('GET', $url, ['timeout' => self::TIMEOUT * 2]);
        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(\sprintf('HTTP %d for %s', $response->getStatusCode(), $url));
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'wp-');
        $handle = fopen($path, 'w') ?: throw new \RuntimeException('Cannot write '.$path);
        foreach ($this->httpClient->stream($response) as $chunk) {
            fwrite($handle, $chunk->getContent());
        }
        fclose($handle);

        return $path;
    }
}
