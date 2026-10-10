<?php

declare(strict_types=1);

namespace App\Flight\Terrain;

use App\Session\CondorClubTasks;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Height of the ground, from the AWS Terrain Tiles (Mapzen, terrarium encoding: elevation =
 * red × 256 + green + blue / 256 − 32768 metres), the relief of the 3D view. Tiles of zoom ZOOM
 * (about 55 m a pixel at 45° N) are downloaded once into `$directory` and read with GD, the
 * elevation interpolated between the four pixels around a point.
 *
 * Not the relief of Condor, but the real one it is built from: close enough for the height above
 * the ground and the slopes. Without the tiles (no network, or `$tiles` empty as in the tests),
 * elevations are null.
 */
class Terrain
{
    private const int ZOOM = 11;
    private const int SIZE = 256;

    /** @var array<string, \GdImage|false> tiles read during this request */
    private array $images = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%app.terrain_dir%')]
        private readonly string $directory,
        #[Autowire('%app.terrain_tiles%')]
        private readonly string $tiles,
    ) {
    }

    /** Metres above sea level, or null when the tile is not available. */
    public function elevation(float $latitude, float $longitude): ?float
    {
        if ('' === $this->tiles) {
            return null;
        }
        $n = 2 ** self::ZOOM;
        $x = ($longitude + 180.0) / 360.0 * $n * self::SIZE;
        $phi = deg2rad($latitude);
        $y = (1.0 - log(tan($phi) + 1.0 / cos($phi)) / \M_PI) / 2.0 * $n * self::SIZE;
        // Pixel centres are at half pixels
        $x -= 0.5;
        $y -= 0.5;
        $x0 = (int) floor($x);
        $y0 = (int) floor($y);
        $fx = $x - $x0;
        $fy = $y - $y0;
        $values = [];
        foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as [$dx, $dy]) {
            $value = $this->pixel($x0 + $dx, $y0 + $dy);
            if (null === $value) {
                return null;
            }
            $values[] = $value;
        }

        return ($values[0] * (1 - $fx) + $values[1] * $fx) * (1 - $fy) + ($values[2] * (1 - $fx) + $values[3] * $fx) * $fy;
    }

    /** Elevation of a pixel of the whole map at ZOOM. */
    private function pixel(int $x, int $y): ?float
    {
        $image = $this->tile(intdiv($x, self::SIZE), intdiv($y, self::SIZE));
        if (false === $image) {
            return null;
        }
        $rgb = imagecolorat($image, $x % self::SIZE, $y % self::SIZE);

        return (($rgb >> 16) & 0xFF) * 256 + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF) / 256 - 32768;
    }

    private function tile(int $x, int $y): \GdImage|false
    {
        $key = $x.'/'.$y;
        if (\array_key_exists($key, $this->images)) {
            return $this->images[$key];
        }
        $path = \sprintf('%s/%d/%d/%d.png', $this->directory, self::ZOOM, $x, $y);
        if (!is_file($path)) {
            try {
                $png = $this->httpClient->request('GET', strtr($this->tiles, ['{z}' => (string) self::ZOOM, '{x}' => (string) $x, '{y}' => (string) $y]), [
                    'headers' => ['User-Agent' => CondorClubTasks::USER_AGENT],
                    'timeout' => 10,
                ])->getContent();
                (new Filesystem())->dumpFile($path, $png);
            } catch (ExceptionInterface $exception) {
                $this->logger->warning('Terrain tile {tile} not available: {error}', ['tile' => $key, 'error' => $exception->getMessage()]);

                return $this->images[$key] = false;
            }
        }
        $image = @imagecreatefrompng($path);
        // Palette images give palette indexes, not colours
        if (false !== $image && !imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        return $this->images[$key] = $image;
    }
}
