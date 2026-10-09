<?php

declare(strict_types=1);

namespace App\Session;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads the settings of a task from Condor Club: its pages (condor.club/task/<id>) load them as
 * JSON from a public address, without an account.
 */
final readonly class CondorClubTasks
{
    public const string API_URL = 'https://condor.club/api/task/%d';

    /** Condor Club refuses requests without a user agent: the site says who it is. */
    public const string USER_AGENT = 'ClubEplaneur/1.0 (+https://club.eplaneur.fr)';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @throws \InvalidArgumentException when Condor Club does not answer with a task
     */
    public function settings(int $id): TaskSettings
    {
        try {
            $task = $this->httpClient->request('GET', \sprintf(self::API_URL, $id), [
                'headers' => ['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT],
                'timeout' => 10,
            ])->toArray();
        } catch (ExceptionInterface $exception) {
            throw new \InvalidArgumentException(\sprintf('Condor Club did not answer for the task %d: %s', $id, $exception->getMessage()), 0, $exception);
        }

        return TaskSettings::fromCondorClub($task);
    }
}
