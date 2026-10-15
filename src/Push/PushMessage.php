<?php

declare(strict_types=1);

namespace App\Push;

/**
 * A notification shown by the service worker (templates/app/service_worker.js.twig): its title, its
 * text, the page it opens, and a tag that replaces an earlier notification of the same tag.
 */
final readonly class PushMessage implements \JsonSerializable
{
    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public ?string $tag = null,
    ) {
    }

    /** @return array{title: string, body: string, url: string, tag: ?string} */
    public function jsonSerialize(): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'tag' => $this->tag];
    }
}
