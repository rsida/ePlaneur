<?php

declare(strict_types=1);

namespace App\WordPress;

/**
 * What the HTML conversion of one page or post needs from the import: where a link now leads, how a
 * file enters the media library, and where to report what could not be converted.
 */
final class ConversionContext
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param \Closure(string): string              $linkResolver  old address → address on the new site
     * @param \Closure(string, ?string): (int|null) $mediaImporter file address and alt text → media id
     * @param string                                $label         "page le-club/statuts", for the warnings
     */
    public function __construct(
        private readonly \Closure $linkResolver,
        private readonly \Closure $mediaImporter,
        public readonly string $label,
    ) {
    }

    public function link(string $url): string
    {
        return ($this->linkResolver)($url);
    }

    public function media(string $url, ?string $alt = null): ?int
    {
        return ($this->mediaImporter)($url, $alt);
    }

    public function warn(string $message): void
    {
        $this->warnings[] = \sprintf('%s : %s', $this->label, $message);
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
