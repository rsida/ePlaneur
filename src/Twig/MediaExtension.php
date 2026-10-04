<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Media;
use App\Media\MediaLibrary;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * `media(id)`, `media_url(media)` and `|file_size` for templates displaying uploaded files.
 */
final readonly class MediaExtension
{
    public function __construct(
        private MediaLibrary $library,
        private UrlGeneratorInterface $urlGenerator,
        private SluggerInterface $slugger,
    ) {
    }

    #[AsTwigFunction('media')]
    public function media(?int $id): ?Media
    {
        return $this->library->get($id);
    }

    #[AsTwigFunction('media_url')]
    public function mediaUrl(Media $media, bool $download = false): string
    {
        $extension = pathinfo($media->getOriginalName(), \PATHINFO_EXTENSION);
        $name = $this->slugger->slug(pathinfo($media->getOriginalName(), \PATHINFO_FILENAME))->lower()->toString();
        $parameters = ['id' => $media->getId(), 'name' => ('' !== $name ? $name : 'fichier').('' !== $extension ? '.'.mb_strtolower($extension) : '')];
        if ($download) {
            $parameters['download'] = 1;
        }

        return $this->urlGenerator->generate('app_media', $parameters);
    }

    /** Size in French units: "248 Ko", "1,2 Mo". */
    #[AsTwigFilter('file_size')]
    public function fileSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => str_replace('.', ',', (string) round($bytes / 1048576, 1)).' Mo',
            $bytes >= 1024 => max(1, (int) round($bytes / 1024)).' Ko',
            default => $bytes.' o',
        };
    }
}
