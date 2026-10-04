<?php

declare(strict_types=1);

namespace App\Content\Block;

/**
 * YouTube or Vimeo video. Only the poster is shown at first; the player (youtube-nocookie.com or
 * player.vimeo.com) is loaded when the reader clicks, so no third-party cookie is set before.
 */
final readonly class VideoBlock implements BlockInterface
{
    public function __construct(
        public string $url,
        public ?string $title = null,
        public ?string $subtitle = null,
        public ?int $posterId = null,
        public ?string $caption = null,
    ) {
    }

    public static function type(): BlockType
    {
        return BlockType::Video;
    }

    public function mediaIds(): array
    {
        return null === $this->posterId ? [] : [$this->posterId];
    }

    /**
     * Player URL with autoplay (the click on the poster is the play request), or null when the URL
     * is neither a YouTube nor a Vimeo video.
     */
    public function embedUrl(): ?string
    {
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
            str_contains($this->url, 'vimeo.com') => 'Vimeo',
            str_contains($this->url, 'youtu') => 'YouTube',
            default => null,
        };
    }
}
