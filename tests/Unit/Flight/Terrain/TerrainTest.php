<?php

declare(strict_types=1);

namespace App\Tests\Unit\Flight\Terrain;

use App\Flight\Terrain\Terrain;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Terrain tiles served by a mock: every pixel encodes the same elevation (terrarium encoding).
 */
final class TerrainTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/terrain-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testTheElevationIsDecodedAndTheTileKept(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $url;

            return new MockResponse(self::tile(1234.5));
        });
        $terrain = new Terrain($client, new NullLogger(), $this->directory, 'https://tiles.test/{z}/{x}/{y}.png');

        self::assertEqualsWithDelta(1234.5, $terrain->elevation(45.0, 5.0), 0.01);
        self::assertSame(['https://tiles.test/11/1052/736.png'], $requests);
        self::assertFileExists($this->directory.'/11/1052/736.png');

        // Another request reads the file kept, not the network
        $again = new Terrain(new MockHttpClient(static fn (): MockResponse => throw new \LogicException('No download expected')), new NullLogger(), $this->directory, 'https://tiles.test/{z}/{x}/{y}.png');
        self::assertEqualsWithDelta(1234.5, $again->elevation(45.0001, 5.0001), 0.01);
    }

    public function testNoElevationWithoutTiles(): void
    {
        $failing = new Terrain(new MockHttpClient(new MockResponse('', ['http_code' => 503])), new NullLogger(), $this->directory, 'https://tiles.test/{z}/{x}/{y}.png');
        self::assertNull($failing->elevation(45.0, 5.0));

        $off = new Terrain(new MockHttpClient(), new NullLogger(), $this->directory, '');
        self::assertNull($off->elevation(45.0, 5.0));
    }

    /** A PNG tile of one elevation. */
    private static function tile(float $elevation): string
    {
        $value = $elevation + 32768;
        $image = imagecreatetruecolor(256, 256);
        imagefill($image, 0, 0, imagecolorallocate($image, (int) floor($value / 256), (int) floor($value) % 256, (int) round(($value - floor($value)) * 256)));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
