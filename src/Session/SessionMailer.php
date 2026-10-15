<?php

declare(strict_types=1);

namespace App\Session;

use App\Entity\Flight;
use App\Entity\NetworkSession;
use App\Entity\PushSubscription;
use App\Entity\User;
use App\Push\PushMessage;
use App\Push\PushNotifier;
use App\Repository\NetworkSessionRepository;
use App\Repository\PushSubscriptionRepository;
use App\Repository\UserRepository;
use App\Security\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Session reminders and results (roadmap 11g, doc/sessions.md#reminders-and-results): by e-mail to
 * the accounts that asked for them (UserNotification::Sessions), and as a notification to the devices
 * that turned them on (doc/installable-site.md#notifications). The day before a session, a reminder;
 * once its results are known, the podium. Each is sent once per session.
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
        private PushNotifier $notifier,
        private PushSubscriptionRepository $subscriptions,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * Reminds the sessions of tomorrow.
     *
     * @return array{sessions: list<NetworkSession>, readers: list<User>, devices: list<PushSubscription>}
     */
    public function remind(bool $dryRun = false): array
    {
        $now = $this->clock->now();
        $sessions = $this->sessions->findToRemind($now->modify('+1 day'));
        $readers = [] !== $sessions ? $this->readers() : [];
        $devices = [] !== $sessions ? $this->devices() : [];
        if (!$dryRun) {
            foreach ($sessions as $session) {
                foreach ($readers as $reader) {
                    $this->send($reader, \sprintf('Demain : %s', self::title($session)), 'email/session_reminder.html.twig', ['session' => $session]);
                }
                $this->notifier->send($devices, new PushMessage(
                    \sprintf('Vol en réseau demain à %s', $session->getStartsAt()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('H\hi')),
                    self::title($session),
                    $this->urls->generate('app_session_show', ['id' => $session->getId()]),
                    'session-'.$session->getId(),
                ));
                $session->markReminderSent($now);
            }
            $this->entityManager->flush();
        }

        return ['sessions' => $sessions, 'readers' => $readers, 'devices' => $devices];
    }

    /**
     * Sends the results of the last sessions that have some: the results of the server, else the
     * tracks uploaded for them.
     *
     * @return array{sessions: list<NetworkSession>, readers: list<User>, devices: list<PushSubscription>}
     */
    public function results(bool $dryRun = false): array
    {
        $now = $this->clock->now();
        $sessions = array_values(array_filter($this->sessions->findResultsToSend($now, self::RESULTS_WAIT), static fn (NetworkSession $session): bool => [] !== self::podium($session)));
        $readers = [] !== $sessions ? $this->readers() : [];
        $devices = [] !== $sessions ? $this->devices() : [];
        if (!$dryRun) {
            foreach ($sessions as $session) {
                $podium = self::podium($session);
                foreach ($readers as $reader) {
                    $this->send($reader, \sprintf('Résultats : %s', self::title($session)), 'email/session_results.html.twig', ['session' => $session, 'podium' => $podium]);
                }
                $this->notifier->send($devices, new PushMessage(
                    'Résultats du vol en réseau',
                    \sprintf('%s : %s', self::title($session), implode(', ', array_map(static fn (array $place): string => $place['rank'].'. '.$place['pilot'], $podium))),
                    $this->urls->generate('app_session_show', ['id' => $session->getId()]),
                    'session-'.$session->getId(),
                ));
                $session->markResultsSent($now);
            }
            $this->entityManager->flush();
        }

        return ['sessions' => $sessions, 'readers' => $readers, 'devices' => $devices];
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
     * The devices that turned the notifications on, of verified accounts.
     *
     * @return list<PushSubscription>
     */
    private function devices(): array
    {
        return array_values(array_filter($this->subscriptions->findOfUsers(), static fn (PushSubscription $device): bool => $device->getUser()->isVerified()));
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
