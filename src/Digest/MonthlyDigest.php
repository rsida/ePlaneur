<?php

declare(strict_types=1);

namespace App\Digest;

use App\Activity\ActivityMonth;
use App\Activity\ActivityReport;
use App\Entity\Flight;
use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Flight\Club\ClubRecords;
use App\Repository\FlightRepository;
use App\Repository\NetworkSessionRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Security\UserNotification;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * The monthly digest (roadmap 11h, doc/monthly-digest.md): the month of the club by e-mail to the
 * accounts that asked for it (UserNotification::News): its activity, the records set, the coming
 * sessions and the posts of the month each reader may see.
 */
final readonly class MonthlyDigest
{
    /** Coming sessions listed. */
    private const int SESSIONS = 6;

    /** Posts of the month listed. */
    private const int POSTS = 6;

    public function __construct(
        private ActivityReport $activity,
        private FlightRepository $flights,
        private NetworkSessionRepository $sessions,
        private PostRepository $posts,
        private UserRepository $users,
        private MailerInterface $mailer,
        private ClockInterface $clock,
    ) {
    }

    /**
     * What every reader gets, for a month (the first day of it).
     *
     * @return array{month: \DateTimeImmutable, activity: ActivityMonth, records: list<array{plan: string, code: ?string, flight: Flight}>, sessions: list<NetworkSession>}
     */
    public function of(\DateTimeImmutable $month): array
    {
        $month = $month->modify('first day of this month')->setTime(0, 0);
        $records = [];
        foreach (ClubRecords::byTask($this->flights->findAll()) as ['plan' => $plan, 'best' => $best]) {
            if ($best->getDate()->format('Y-m') === $month->format('Y-m') && true === $best->getOwner()?->isInRanking()) {
                $records[] = ['plan' => $plan, 'code' => FlightPlan::codeIn($plan), 'flight' => $best];
            }
        }

        return [
            'month' => $month,
            'activity' => $this->activity->month($month),
            'records' => $records,
            'sessions' => $this->sessions->findUpcoming($this->clock->now(), self::SESSIONS),
        ];
    }

    /**
     * Sends the digest of a month to every account that asked for it; nothing when the month and
     * the coming weeks are empty.
     *
     * @return list<User> the readers it was sent to (or would be, with $dryRun)
     */
    public function send(\DateTimeImmutable $month, bool $dryRun = false): array
    {
        $digest = $this->of($month);
        if (!self::hasNews($digest)) {
            return [];
        }
        $readers = array_values(array_filter($this->users->findBy(['verified' => true]), static fn (User $user): bool => $user->wants(UserNotification::News)));
        if ($dryRun) {
            return $readers;
        }
        $label = self::monthLabel($digest['month']);
        foreach ($readers as $reader) {
            $posts = iterator_to_array($this->posts->paginateNews($reader, null, (int) $digest['month']->format('Y'), (int) $digest['month']->format('n'), 1, self::POSTS));
            $this->mailer->send((new TemplatedEmail())
                ->to(new Address($reader->getEmail(), $reader->getDisplayName()))
                ->subject(\sprintf('Le club en %s', $label))
                ->htmlTemplate('email/monthly_digest.html.twig')
                ->context($digest + ['reader' => $reader, 'posts' => $posts, 'label' => $label]));
        }

        return $readers;
    }

    /**
     * @param array{activity: ActivityMonth, records: list<mixed>, sessions: list<NetworkSession>} $digest
     */
    private static function hasNews(array $digest): bool
    {
        $activity = $digest['activity'];

        return [] !== $digest['records'] || [] !== $digest['sessions'] || $activity->flights > 0 || $activity->sessions > 0 || $activity->steps > 0;
    }

    /** "septembre 2026". */
    private static function monthLabel(\DateTimeImmutable $month): string
    {
        $names = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $names[(int) $month->format('n') - 1].' '.$month->format('Y');
    }
}
