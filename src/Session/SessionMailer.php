<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\Flight;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Repository\NetworkSessionRepository;
use App\Repository\UserRepository;
use App\Security\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Session reminders and results by e-mail (roadmap 11g, doc/sessions.md#reminders-and-results), to
 * the accounts that asked for them (UserNotification::Sessions): the day before a session, a
 * reminder; once its results are known, the podium. Each is sent once per session.
 */
final readonly class SessionMailer
{
    /** Days after a session during which its results are waited for. */
    public const int RESULTS_WAIT = 3;

    /** Places of the podium. */
    private const int PODIUM = 3;

    public function __construct(
        private NetworkSessionRepository $sessions,
        private UserRepository $users,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Reminds the sessions of tomorrow.
     *
     * @return array{sessions: list<NetworkSession>, readers: list<User>}
     */
    public function remind(bool $dryRun = false): array
    {
        $now = $this->clock->now();
        $sessions = $this->sessions->findToRemind($now->modify('+1 day'));
        $readers = [] !== $sessions ? $this->readers() : [];
        if (!$dryRun) {
            foreach ($sessions as $session) {
                foreach ($readers as $reader) {
                    $this->send($reader, \sprintf('Demain : %s', self::title($session)), 'email/session_reminder.html.twig', ['session' => $session]);
                }
                $session->markReminderSent($now);
            }
            $this->entityManager->flush();
        }

        return ['sessions' => $sessions, 'readers' => $readers];
    }

    /**
     * Sends the results of the last sessions that have some: the results of the server, else the
     * tracks uploaded for them.
     *
     * @return array{sessions: list<NetworkSession>, readers: list<User>}
     */
    public function results(bool $dryRun = false): array
    {
        $now = $this->clock->now();
        $sessions = array_values(array_filter($this->sessions->findResultsToSend($now, self::RESULTS_WAIT), static fn (NetworkSession $session): bool => [] !== self::podium($session)));
        $readers = [] !== $sessions ? $this->readers() : [];
        if (!$dryRun) {
            foreach ($sessions as $session) {
                foreach ($readers as $reader) {
                    $this->send($reader, \sprintf('Résultats : %s', self::title($session)), 'email/session_results.html.twig', ['session' => $session, 'podium' => self::podium($session)]);
                }
                $session->markResultsSent($now);
            }
            $this->entityManager->flush();
        }

        return ['sessions' => $sessions, 'readers' => $readers];
    }

    /**
     * The first pilots who finished: from the results of the server, else from the tracks.
     *
     * @return list<array{rank: int, pilot: string, speed: ?float}>
     */
    public static function podium(NetworkSession $session): array
    {
        $finished = array_values(array_filter($session->getResults(), static fn (array $result): bool => null !== $result['speed'] && $result['speed'] > 0));
        if ([] !== $finished) {
            usort($finished, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);

            return array_map(static fn (array $result): array => ['rank' => $result['rank'], 'pilot' => $result['player'], 'speed' => $result['speed']], \array_slice($finished, 0, self::PODIUM));
        }
        $flights = array_values(array_filter($session->getFlights()->toArray(), static fn (Flight $flight): bool => null !== $flight->getTaskSpeed()));
        usort($flights, static fn (Flight $a, Flight $b): int => $b->getTaskSpeed() <=> $a->getTaskSpeed());

        return array_map(static fn (Flight $flight, int $i): array => ['rank' => $i + 1, 'pilot' => $flight->getOwner()?->getDisplayName() ?? $flight->getPilot(), 'speed' => $flight->getTaskSpeed()], \array_slice($flights, 0, self::PODIUM), array_keys(\array_slice($flights, 0, self::PODIUM)));
    }

    /** @return list<User> */
    private function readers(): array
    {
        return array_values(array_filter($this->users->findBy(['verified' => true]), static fn (User $user): bool => $user->wants(UserNotification::Sessions)));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(User $reader, string $subject, string $template, array $context): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->to(new Address($reader->getEmail(), $reader->getDisplayName()))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context + ['reader' => $reader]));
    }

    private static function title(NetworkSession $session): string
    {
        $plan = $session->getFlightPlan();

        return \sprintf('%s (%s)', $plan->getTitle() ?? $plan->getCode(), $plan->getCategory());
    }
}
