<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Flight;
use App\Flight\Analysis\Launch;
use App\Flight\FlightImporter;
use App\Tests\Unit\Flight\FlightSamples;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * app:flight:analyse: stored flights are analysed again from their files.
 */
final class FlightAnalyseCommandTest extends KernelTestCase
{
    private FlightImporter $importer;
    private EntityManagerInterface $entityManager;
    private Flight $flight;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->importer = static::getContainer()->get(FlightImporter::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $file = sys_get_temp_dir().'/sample.igc';
        file_put_contents($file, FlightSamples::igc(FlightSamples::glideThermalGlide()));
        $this->flight = $this->importer->import($file, 'sample.igc', null);
    }

    protected function tearDown(): void
    {
        @unlink($this->importer->pathOf($this->flight));
        parent::tearDown();
    }

    public function testFlightsStoredBeforeAChangeOfTheAnalysisGetTheNewValues(): void
    {
        // A flight analysed before the launch was read
        $this->entityManager->getConnection()->executeStatement('UPDATE flight SET launch = NULL');
        $this->entityManager->clear();

        self::assertSame(Command::SUCCESS, $this->execute());
        $this->entityManager->clear();
        self::assertSame(Launch::Airborne, $this->entityManager->find(Flight::class, $this->flight->getId())?->getLaunch());
    }

    public function testAMissingFileIsReported(): void
    {
        unlink($this->importer->pathOf($this->flight));

        self::assertSame(Command::FAILURE, $this->execute());
    }

    private function execute(): int
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('app:flight:analyse'));

        return $tester->execute([]);
    }
}
