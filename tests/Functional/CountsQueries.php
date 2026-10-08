<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DataCollector\DoctrineDataCollector;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Number of database reads of a request, to check that a list does not run one query per item.
 * Compare two requests after a first one: the first also logs the queries of the test.
 */
trait CountsQueries
{
    private function countReads(KernelBrowser $client, string $uri): int
    {
        $client->enableProfiler();
        $client->request('GET', $uri);
        self::assertResponseIsSuccessful();
        $profile = $client->getProfile();
        \assert(false !== $profile);
        $collector = $profile->getCollector('db');
        \assert($collector instanceof DoctrineDataCollector);

        // Reads only: the writes of the test may be logged too
        return \count(array_filter($collector->getQueries()['default'] ?? [], static fn (array $query): bool => str_starts_with((string) $query['sql'], 'SELECT')));
    }
}
