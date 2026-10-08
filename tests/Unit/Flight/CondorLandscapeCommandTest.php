<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight;

use App\Command\CondorLandscapeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * app:condor:landscape: the configuration line from the header of a landscape's .trn file.
 */
final class CondorLandscapeCommandTest extends TestCase
{
    public function testTheProjectionIsReadFromTheHeader(): void
    {
        $file = sys_get_temp_dir().'/AA3.trn';
        // Header of the AA3 height map
        file_put_contents($file, pack('llgggggla', 10240, 6400, 90.0, -90.0, 90.0, 1059840.0, 4786560.0, 32, 'N').str_repeat("\0", 16));
        $application = new Application();
        $application->addCommand(new CondorLandscapeCommand());
        $tester = new CommandTester($application->find('app:condor:landscape'));

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $file]));
        self::assertStringContainsString('AA3: { utm_zone: 32, northern: true, easting_origin: 1059840, northing_origin: 4786560 }', $tester->getDisplay());
    }
}
