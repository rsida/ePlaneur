<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;

/**
 * Stores uploaded files under var/uploads (a Docker volume in production) and creates their Media.
 * Files are named by a random token so names never collide nor reveal the original name.
 */
final readonly class MediaStorage
{
    public function __construct(
        #[Autowire('%app.uploads_dir%')]
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Copies a local file into the storage (fixtures, imports). The source file is left untouched.
     * The returned Media is not persisted.
     */
    public function storeCopy(string $sourcePath, ?string $originalName = null): Media
    {
        if (!is_file($sourcePath)) {
            throw new \InvalidArgumentException(\sprintf('File "%s" not found.', $sourcePath));
        }

        return $this->store($sourcePath, $originalName ?? basename($sourcePath), move: false);
    }

    /**
     * Moves a file uploaded from the back-office into the storage. The returned Media is not persisted.
     */
    public function storeUpload(UploadedFile $file): Media
    {
        return $this->store($file->getPathname(), $file->getClientOriginalName(), move: true);
    }

    private function store(string $sourcePath, string $originalName, bool $move): Media
    {
        $mimeType = MimeTypes::getDefault()->guessMimeType($sourcePath) ?? 'application/octet-stream';
        $extension = mb_strtolower(pathinfo($originalName, \PATHINFO_EXTENSION)) ?: (MimeTypes::getDefault()->getExtensions($mimeType)[0] ?? 'bin');
        $relativePath = \sprintf('%s/%s.%s', date('Y/m'), bin2hex(random_bytes(16)), $extension);
        $target = $this->absolutePath($relativePath);
        $size = (int) filesize($sourcePath);

        if ($move) {
            $this->filesystem->mkdir(\dirname($target));
            $this->filesystem->rename($sourcePath, $target);
        } else {
            $this->filesystem->copy($sourcePath, $target);
        }

        $media = new Media($relativePath, $originalName, $mimeType, $size);
        $this->describe($media, $target);

        return $media;
    }

    public function pathOf(Media $media): string
    {
        return $this->absolutePath($media->getPath());
    }

    /** Removes every stored file. Only for the dev fixtures, which empty the media table too. */
    public function purge(): void
    {
        $this->filesystem->remove($this->directory);
    }

    /**
     * Where the resized version of a picture is kept (see ImageVariants): next to the uploads, under
     * cache/<filter>/, with the same random name.
     */
    public function variantPath(Media $media, string $filter, string $extension): string
    {
        return $this->absolutePath(\sprintf('cache/%s/%s.%s', $filter, preg_replace('/\.[^.\/]+$/', '', $media->getPath()), $extension));
    }

    /** Removes the file and its resized versions. */
    public function delete(Media $media): void
    {
        $this->filesystem->remove($this->pathOf($media));
        foreach (ImageVariants::FILTERS as $filter) {
            $this->filesystem->remove($this->variantPath($media, $filter, ImageVariants::EXTENSION));
        }
    }

    private function absolutePath(string $relativePath): string
    {
        return $this->directory.'/'.$relativePath;
    }

    /** Reads dimensions of pictures and the page count of PDF files. */
    private function describe(Media $media, string $path): void
    {
        if ($media->isImage()) {
            $size = @getimagesize($path);
            if (false !== $size) {
                $media->setDimensions($size[0], $size[1]);
            }
        }

        if ($media->isPdf()) {
            $content = (string) file_get_contents($path);
            $pages = preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $content);
            $media->setPages($pages ?: null);
        }
    }
}
