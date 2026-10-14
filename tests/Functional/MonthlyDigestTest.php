<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Entity\FlightPlan;
use App\Entity\NetworkSession;
use App\Entity\Post;
use App\Entity\User;
use App\Flight\Analysis\FlightAnalyzer;
use App\Flight\Track;
use App\Flight\Turnpoint;
use App\Security\UserNotification;
use App\Tests\Unit\Flight\FlightSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The monthly digest: sent to the accounts that asked for it, with the month of the club.
 */
final class MonthlyDigestTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        static::mockTime('2026-10-01 08:00');
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testTheDigestOfTheLastMonthGoesToTheReadersWhoAskedForIt(): void
    {
        $lea = $this->user('lea@example.org', 'Léa')->setNotifications([UserNotification::News]);
        $this->user('max@example.org', 'Max');
        $this->user('zoe@example.org', 'Zoé')->setNotifications([UserNotification::News])->setVerified(false);
        $this->flight($lea, '2026-09-12');
        $plan = $this->entityManager->getRepository(FlightPlan::class)->findOneBy(['code' => 'C2-d015']) ?? new FlightPlan('C2-d015');
        $this->entityManager->persist($plan);
        $this->entityManager->persist((new NetworkSession($plan, new \DateTimeImmutable('2026-10-07'), new \DateTimeImmutable()))->setStartTime(new \DateTimeImmutable('16:30')));
        $this->entityManager->persist((new Post('Le championnat commence', 'championnat'))->setExcerpt('Résumé.')->setPublishedAt(new \DateTimeImmutable('2026-09-20 10:00')));
        $this->entityManager->persist((new Post('Un article d’août', 'aout'))->setExcerpt('Résumé.')->setPublishedAt(new \DateTimeImmutable('2026-08-20 10:00')));
        $this->entityManager->flush();

        $command = $this->command();
        $command->execute(['--dry-run' => true]);
        self::assertStringContainsString('Would send to lea@example.org', $command->getDisplay());
        self::assertQueuedEmailCount(0);

        $command->execute([]);
        $command->assertCommandIsSuccessful();
        self::assertStringContainsString('1 digest(s) of 2026-09 sent.', $command->getDisplay(), 'Max did not ask for it, Zoé has not confirmed her address');
        self::assertQueuedEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'lea@example.org');
        self::assertEmailSubjectContains($email, 'Le club en septembre 2026');
        self::assertEmailHtmlBodyContains($email, '1 vol déposé');
        self::assertEmailHtmlBodyContains($email, 'C2-d015</a> : Léa');
        self::assertEmailHtmlBodyContains($email, 'Mercredi 7 octobre 2026 à 16h30');
        self::assertEmailHtmlBodyContains($email, 'Le championnat commence');
        self::assertEmailHtmlBodyNotContains($email, 'Un article d’août');
    }

    public function testAnEmptyMonthSendsNothing(): void
    {
        $this->user('lea@example.org', 'Léa')->setNotifications([UserNotification::News]);
        $this->entityManager->flush();

        $command = $this->command();
        $command->execute(['--month' => '2025-02']);
        self::assertStringContainsString('0 digest(s) of 2025-02 sent.', $command->getDisplay());
        self::assertQueuedEmailCount(0);
    }

    private function command(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel ?? throw new \LogicException()))->find('app:digest:send'));
    }

    private function user(string $email, string $name): User
    {
        $user = (new User())->setEmail($email)->setDisplayName($name)->setVerified(true)->setPassword('x');
        $this->entityManager->persist($user);

        return $user;
    }

    private function flight(User $owner, string $date): void
    {
        $point = static fn (string $name, float $x, float $radius): Turnpoint => new Turnpoint($name, ...FlightSamples::toLatLon($x, 0), altitude: 0.0, radius: $radius);
        $track = new Track('igc', 'Condor 2', 'Léa', null, 'ASK21', 'AA2', null, 'C2-d015 ePlaneur', [$point('Terrain', 0, 3000), $point('Départ', 0, 500), $point('Arrivée', 7000, 500)], FlightSamples::glideThermalGlide(), true, new \DateTimeImmutable($date));
        $this->entityManager->persist(new Flight((new FlightAnalyzer())->analyze($track), 'file.igc', 'file.igc', $owner, new \DateTimeImmutable($date)));
    }
}
