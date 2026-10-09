<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\NetworkSession;
use App\Repository\NetworkSessionRepository;
use App\Session\SessionResults;
use Symfony\Component\Clock\ClockInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Network sessions in templates: the coming sessions (dashboard, home) and the status of a pilot in
 * the results of the server.
 */
final readonly class SessionExtension
{
    public function __construct(
        private NetworkSessionRepository $sessions,
        private ClockInterface $clock,
    ) {
    }

    /** @return list<NetworkSession> */
    #[AsTwigFunction('upcoming_sessions')]
    public function upcoming(int $limit = 3): array
    {
        return $this->sessions->findUpcoming($this->clock->now(), $limit);
    }

    #[AsTwigFilter('session_status')]
    public static function status(string $status): string
    {
        return SessionResults::statusLabel($status);
    }
}
