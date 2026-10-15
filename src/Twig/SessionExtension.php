<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\NetworkSession;
use App\Repository\NetworkSessionRepository;
use App\Session\ResultNames;
use App\Session\SessionResults;
use Symfony\Component\Clock\ClockInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Network sessions in templates: the coming sessions (dashboard, home) and the status of a pilot in
 * the results of the server, those results with the names the pilots chose.
 */
final readonly class SessionExtension
{
    public function __construct(
        private NetworkSessionRepository $sessions,
        private ClockInterface $clock,
        private ResultNames $names,
    ) {
    }

    /**
     * The results of the server with the names the pilots chose.
     *
     * @return list<array{rank: int, status: string, player: string, competitionId: ?string, registration: ?string, glider: ?string, distance: ?int, time: ?int, speed: ?float, penalty: ?float, score: ?float}>
     */
    #[AsTwigFunction('session_results')]
    public function results(NetworkSession $session): array
    {
        return $this->names->of($session);
    }

    /** @return list<NetworkSession> */
    #[AsTwigFunction('upcoming_sessions')]
    public function upcoming(int $limit = 3): array
    {
        return $this->sessions->findUpcoming($this->clock->now(), $limit);
    }

    /** @return list<NetworkSession> the latest past sessions */
    #[AsTwigFunction('recent_sessions')]
    public function recent(int $limit = 3): array
    {
        return $this->sessions->findPast($this->clock->now(), [], 1, $limit)['sessions'];
    }

    /** A time of day given in minutes after midnight: 765 → "12h45". */
    #[AsTwigFilter('day_minutes')]
    public static function dayMinutes(int $minutes): string
    {
        return \sprintf('%dh%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    }

    #[AsTwigFilter('session_status')]
    public static function status(string $status): string
    {
        return SessionResults::statusLabel($status);
    }
}
