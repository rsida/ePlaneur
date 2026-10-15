<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Entity\User;
use App\Security\UserNotification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Session reminders and results by e-mail: the day before, then once the results are known, to the
 * accounts that asked for them, once each.
 */
final class SessionMailTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        static::mockTime('2026-10-13 18:00');
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testTheReminderAndTheResultsAreSentOnce(): void
    {
        $this->user('lea@example.org', [UserNotification::Sessions]);
        $this->user('max@example.org', []);
        $this->session('2026-10-14', '16:30');
        $this->session('2026-10-12', '16:30', [
            $this->place(2, 'Max Martin', 118.2),
            $this->place(1, 'Alice Dupont', 121.4),
            $this->place(4, 'Paul Durand', null),
            $this->place(3, 'Léa Petit', 101.0),
        ]);
        $this->session('2026-10-11', '21:00');
        $this->session('2026-10-01', '16:30', [$this->place(1, 'Ancien', 99.0)]);

        $command = $this->command();
        $command->execute(['--dry-run' => true]);
        self::assertStringContainsString('Reminder of the session of 2026-10-14 (C2-d015): 1 reader(s), not sent', $command->getDisplay());
        self::assertStringContainsString('Results of the session of 2026-10-12 (C2-d015): 1 reader(s), not sent', $command->getDisplay());
        self::assertStringNotContainsString('2026-10-11', $command->getDisplay(), 'No results yet');
        self::assertStringNotContainsString('2026-10-01', $command->getDisplay(), 'Too old');
        self::assertQueuedEmailCount(0);

        $command->execute([]);
        self::assertQueuedEmailCount(2, message: 'Max did not ask for them');
        $reminder = self::getMailerMessage(0);
        self::assertEmailAddressContains($reminder, 'To', 'lea@example.org');
        self::assertEmailSubjectContains($reminder, 'Demain : C2-d015 (Initiation)');
        self::assertEmailHtmlBodyContains($reminder, 'mercredi 14 octobre');
        self::assertEmailHtmlBodyContains($reminder, '16h30');
        self::assertEmailHtmlBodyContains($reminder, '/sessions/');
        self::assertEmailHtmlBodyContains($reminder, '/voler/vols-reseau/prerequis');
        $results = self::getMailerMessage(1);
        self::assertEmailSubjectContains($results, 'Résultats : C2-d015 (Initiation)');
        self::assertEmailHtmlBodyContains($results, '4 pilotes');
        self::assertEmailHtmlBodyContains($results, 'Alice Dupont');
        self::assertEmailHtmlBodyContains($results, '121,4 km/h');
        self::assertEmailHtmlBodyContains($results, 'Léa Petit');
        self::assertEmailHtmlBodyNotContains($results, 'Paul Durand', 'Did not finish: not on the podium');

        $command->execute([]);
        self::assertQueuedEmailCount(2, message: 'Nothing sent twice');
        self::assertStringNotContainsString('Reminder of', $command->getDisplay());
    }

    private function command(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('app:sessions:mail'));
    }

    /**
     * @param list<UserNotification> $notifications
     */
    private function user(string $email, array $notifications): void
    {
        $this->entityManager->persist((new User())->setEmail($email)->setDisplayName($email)->setVerified(true)->setPassword('x')->setNotifications($notifications));
        $this->entityManager->flush();
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function session(string $date, string $time, array $results = []): void
    {
        $plan = $this->entityManager->getRepository(FlightPlan::class)->findOneBy(['code' => 'C2-d015']) ?? new FlightPlan('C2-d015');
        $this->entityManager->persist($plan);
        $session = (new NetworkSession($plan, new \DateTimeImmutable($date), new \DateTimeImmutable()))->setStartTime(new \DateTimeImmutable($time));
        if ([] !== $results) {
            $session->setResults('DSRace-'.random_int(1, 999999).'-on-'.$date, $results);
        }
        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }

    /**
     * @return array<string, mixed>
     */
    private function place(int $rank, string $player, ?float $speed): array
    {
        return ['rank' => $rank, 'status' => null !== $speed ? 'Finished' : 'Landed', 'player' => $player, 'competitionId' => null, 'registration' => null, 'glider' => 'ASK21', 'distance' => 50000, 'time' => null !== $speed ? 1500 : null, 'speed' => $speed, 'penalty' => 0.0, 'score' => null !== $speed ? 1000.0 : 0.0];
    }
}
