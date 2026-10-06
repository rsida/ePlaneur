<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Media;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Model\Binary;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Resized versions of the pictures of the media library, made with the LiipImagine filter sets of
 * config/packages/liip_imagine.yaml (WebP). A version is made the first time it is asked for, then
 * kept next to the uploads (MediaStorage::variantPath()); MediaController serves it with the access
 * rules of the original file.
 *
 * Vector (SVG) and animated (GIF) pictures are always served as uploaded.
 */
final readonly class ImageVariants
{
    /** Filter sets, from the smallest to the largest. */
    public const array FILTERS = ['thumb', 'card', 'content', 'wide'];

    /** Format of every version (the `format` of the filter sets). */
    public const string EXTENSION = 'webp';

    private const array RESIZABLE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private MediaStorage $storage,
        private FilterManager $filterManager,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function supports(Media $media): bool
    {
        return \in_array($media->getMimeType(), self::RESIZABLE_TYPES, true);
    }

    /**
     * Absolute path of the resized version, made now if needed.
     *
     * @throws \InvalidArgumentException for an unknown filter or a picture that is not resized
     */
    public function path(Media $media, string $filter): string
    {
        if (!\in_array($filter, self::FILTERS, true) || !$this->supports($media)) {
            throw new \InvalidArgumentException(\sprintf('No "%s" version for media %d.', $filter, $media->getId()));
        }

        $target = $this->storage->variantPath($media, $filter, self::EXTENSION);
        if (!is_file($target)) {
            $source = new Binary((string) file_get_contents($this->storage->pathOf($media)), $media->getMimeType());
            $resized = $this->filterManager->applyFilter($source, $filter);
            // Written aside then renamed: a reader never gets a half-written file
            $this->filesystem->dumpFile($target, $resized->getContent());
        }

        return $target;
    }
}
