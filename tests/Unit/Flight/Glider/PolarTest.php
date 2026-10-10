<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight\Glider;

use App\Flight\Glider\Polar;
use PHPUnit\Framework\TestCase;

final class PolarTest extends TestCase
{
    public function testACondorPolarFileIsRead(): void
    {
        $polar = Polar::fromCondor(GliderSamples::pol());

        self::assertNotNull($polar);
        self::assertCount(5, $polar->nodes);
        self::assertSame([80.0, -0.5, 70.0, -0.5, 90.0, -0.5], $polar->nodes[1]);
        self::assertNull(Polar::fromCondor('FTR'), 'Not a polar');
        self::assertNull(Polar::fromCondor(pack('l', 5).pack('g*', 1.0, 2.0)), 'Cut short');
    }

    public function testTheCurveGoesThroughItsNodes(): void
    {
        $polar = new Polar(GliderSamples::NODES);

        self::assertEqualsWithDelta(0.5, $polar->sinkAt(80.0), 0.01);
        self::assertEqualsWithDelta(0.95, $polar->sinkAt(130.0), 0.01);
        self::assertNull($polar->sinkAt(300.0), 'Beyond the curve');

        $minimum = $polar->minimumSink();
        self::assertEqualsWithDelta(0.5, $minimum['sink'], 0.01);
        self::assertEqualsWithDelta(80.0, $minimum['speed'], 3.0);
    }

    public function testAHeavierGliderGlidesAsFarButFaster(): void
    {
        $polar = new Polar(GliderSamples::NODES);
        $light = $polar->bestGlide();
        // 1.44 times the mass: speeds and sink rates × 1.2
        $heavy = $polar->bestGlide(1.44);

        self::assertGreaterThan(40.0, $light['ratio']);
        self::assertEqualsWithDelta($light['ratio'], $heavy['ratio'], 0.01);
        self::assertEqualsWithDelta($light['speed'] * 1.2, $heavy['speed'], 0.01);
        self::assertEqualsWithDelta($polar->sinkAt(100.0) * 1.2, $polar->sinkAt(120.0, 1.44), 0.001);
    }

    public function testTheMacCreadySpeedGrowsWithTheClimb(): void
    {
        $polar = new Polar(GliderSamples::NODES);
        $weak = $polar->speedToFly(1.0);
        $strong = $polar->speedToFly(3.0);

        self::assertGreaterThan($polar->bestGlide()['speed'], $weak['speed']);
        self::assertGreaterThan($weak['speed'] + 20, $strong['speed']);
        self::assertGreaterThan($weak['average'], $strong['average']);
        // Average speed of a climb at 3 m/s and glides at that speed
        self::assertEqualsWithDelta($strong['speed'] * 3 / (3 + $strong['sink']), $strong['average'], 0.01);
    }

    public function testTheCurveOfAChartStaysWithinItsSpeeds(): void
    {
        $curve = (new Polar(GliderSamples::NODES))->curve(1.0, 66.0, 270.0, 10);

        self::assertSame(66.0, $curve[0][0]);
        self::assertSame(246.0, end($curve)[0], 'The curve ends at 250 km/h');
    }
}
