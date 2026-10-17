<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * A video: a file of the media library (MP4, WebM), read by the player of the browser, or a YouTube or
 * Vimeo video. For YouTube and Vimeo only the poster is shown at first; their player
 * (youtube-nocookie.com or player.vimeo.com) is loaded when the reader clicks, so no third-party
 * cookie is set before. The file wins when both are given.
 */
final readonly class VideoBlock implements BlockInterface
{
    public function __construct(
        #[Field('Adresse YouTube ou Vimeo', widget: 'url', help: 'Ou choisissez un fichier vidéo de la médiathèque ci-dessous.')]
        public ?string $url = null,
        #[Field('Fichier vidéo', widget: 'media', accept: 'video', help: 'MP4 ou WebM, lu par le lecteur du site.')]
        public ?int $mediaId = null,
        #[Field('Titre')]
        public ?string $title = null,
        #[Field('Sous-titre')]
        public ?string $subtitle = null,
        #[Field('Image d’attente', widget: 'media', accept: 'image', help: 'Vide : la vignette de la vidéo.')]
        public ?int $posterId = null,
        #[Field('Légende')]
        public ?string $caption = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Video;
    }

    public function mediaIds(): array
    {
        return array_values(array_filter([$this->mediaId, $this->posterId], static fn (?int $id): bool => null !== $id));
    }

    /**
     * Player URL with autoplay (the click on the poster is the play request), or null when the URL
     * is neither a YouTube nor a Vimeo video.
     */
    public function embedUrl(): ?string
    {
        if (null !== $this->mediaId || null === $this->url) {
            return null;
        }
        if (1 === preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $this->url, $matches)) {
            return 'https://www.youtube-nocookie.com/embed/'.$matches[1].'?autoplay=1&rel=0';
        }

        if (1 === preg_match('~vimeo\.com/(?:video/)?(\d+)~', $this->url, $matches)) {
            return 'https://player.vimeo.com/video/'.$matches[1].'?autoplay=1&dnt=1';
        }

        return null;
    }

    public function provider(): ?string
    {
        return match (true) {
            null !== $this->mediaId, null === $this->url => null,
            str_contains($this->url, 'vimeo.com') => 'Vimeo',
            str_contains($this->url, 'youtu') => 'YouTube',
            default => null,
        };
    }
}
