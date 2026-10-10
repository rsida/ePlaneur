<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight\Glider;

use App\Flight\Glider\CondorGliders;
use PHPUnit\Framework\TestCase;

final class CondorGlidersTest extends TestCase
{
    public function testTheSheetOfAGliderIsRead(): void
    {
        $sheet = CondorGliders::sheet(GliderSamples::SHEET);

        self::assertNotNull($sheet);
        self::assertSame('Acme Tester Glider-15', $sheet['model']);
        self::assertSame(15.0, $sheet['span']);
        self::assertSame(10.0, $sheet['wingArea']);
        self::assertSame(220.0, $sheet['emptyMass']);
        self::assertSame(500.0, $sheet['maxMass']);
        self::assertSame(180, $sheet['waterBallast']);
        self::assertSame(30.0, $sheet['minWingLoading']);
        self::assertSame(48.0, $sheet['maxWingLoading']);
        self::assertSame(270.0, $sheet['maxSpeed']);
        self::assertSame(66.0, $sheet['minSpeed']);
        self::assertSame(45.0, $sheet['bestGlide']);
        self::assertSame(100.0, $sheet['bestGlideSpeed']);
        self::assertSame(110, $sheet['handicap']);
        self::assertNull($sheet['engine']);
    }

    public function testSheetsWithOtherLabelsAreRead(): void
    {
        // No water, one wing loading, "mass" instead of "weight", an engine
        $sheet = CondorGliders::sheet("Maker Trainer\nWing span, 12 m\nWing area, 12 m2\nEmpty mass, 200 kg\nMax mass, 320 kg\nWater ballast, 0 l\nWing loading, 24.2 kg/m2\nMax speed, 200 km/h\nEngine thrust, 420 N\nFuel capacity, 42 l\n");

        self::assertNotNull($sheet);
        self::assertSame(0, $sheet['waterBallast']);
        self::assertSame(24.2, $sheet['minWingLoading']);
        self::assertSame(26.7, $sheet['maxWingLoading'], 'The maximum mass over the wing area');
        self::assertSame('Poussée 420 N, carburant 42 l', $sheet['engine']);

        // Without any wing loading: empty with a pilot of 90 kg
        $sheet = CondorGliders::sheet("Maker Trainer\nWing span, 12 m\nWing area, 14 m2\nEmpty weight, 190 kg\nMax weight, 318 kg\nMax speed, 183 km/h\n");
        self::assertSame(20.0, $sheet['minWingLoading'] ?? null);

        self::assertNull(CondorGliders::sheet("Tow plane\nWing span, 10 m\n"), 'Not a glider sheet');
    }

    public function testAPlanesFolderIsRead(): void
    {
        $directory = sys_get_temp_dir().'/planes-'.bin2hex(random_bytes(4));
        mkdir($directory.'/Glider-15', recursive: true);
        file_put_contents($directory.'/Glider-15/Glider-15.txt', GliderSamples::SHEET);
        file_put_contents($directory.'/Glider-15/Glider-15.pol', GliderSamples::pol());
        // A tow plane: no polar
        mkdir($directory.'/Towplane');
        file_put_contents($directory.'/Towplane/Towplane.txt', GliderSamples::SHEET);

        $gliders = (new CondorGliders())->read($directory);

        self::assertSame(['Glider-15'], array_keys($gliders));
        self::assertCount(5, $gliders['Glider-15']['polar']->nodes);
    }
}
