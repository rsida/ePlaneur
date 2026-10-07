<?php

declare(strict_types=1);

namespace App\WordPress;

use App\Content\Block as B;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Turns the HTML of a WordPress page or post (Gutenberg blocks and Shortcodes Ultimate spoilers, as
 * rendered by the REST API) into content blocks of the site:
 *
 * | WordPress | Block |
 * |---|---|
 * | h1, h2 | section (numbered, in the table of contents) |
 * | h3 to h6 | heading (h3 large) |
 * | paragraphs, lists, preformatted text | text (consecutive ones merged, cut by separators) |
 * | image | image |
 * | table | table |
 * | file | downloads (consecutive ones merged) |
 * | YouTube / Vimeo embed | video |
 * | uploaded video or audio (MP4, MP3...) | downloads (the site does not host players) |
 * | spoiler, accordion, details | questions (FAQ); files and pictures inside follow it |
 * | quote | quote |
 * | buttons | links |
 * | columns, groups | their content, in order |
 *
 * Dynamic WordPress blocks (post lists, forms, categories) and anything else that cannot be
 * converted are left out and reported as warnings. Links and pictures go through the callbacks of
 * the context: link addresses to their new place, files into the media library.
 */
final readonly class HtmlConverter
{
    /** Containers whose content is converted in place. */
    private const array TRANSPARENT = ['div', 'section', 'article', 'main', 'span', 'center'];

    /** WordPress output with no equivalent here (dynamic lists, forms, styles). */
    private const array DYNAMIC_CLASSES = ['wp-block-query', 'wp-block-categories', 'wp-block-latest-posts', 'wp-block-archives', 'wpforms-container', 'wp-block-search'];

    public function __construct(
        #[Target('app.rich_text')]
        private HtmlSanitizerInterface $richText,
    ) {
    }

    /**
     * @return list<B\BlockInterface>
     */
    public function convert(string $html, ConversionContext $context): array
    {
        $document = \Dom\HTMLDocument::createFromString('<!DOCTYPE html><html><body>'.$html.'</body></html>', \LIBXML_NOERROR);
        $body = $document->body ?? throw new \LogicException('No body.');

        foreach ($body->querySelectorAll('a[href]') as $link) {
            $link->setAttribute('href', $context->link((string) $link->getAttribute('href')));
        }

        $converter = new ConversionRun($this, $context);
        $converter->children($body);

        return $converter->finish();
    }

    /** Limited HTML of a text block: paragraphs, emphasis, links, lists, code. */
    public function richText(string $html): string
    {
        return trim($this->richText->sanitize($html));
    }

    /**
     * @internal used by ConversionRun
     */
    public function isTransparent(\Dom\Element $element): bool
    {
        return \in_array($element->localName, self::TRANSPARENT, true) || \in_array($element->localName, ['figure'], true) && [] === $this->classes($element);
    }

    /**
     * @internal used by ConversionRun
     */
    public function isDynamic(\Dom\Element $element): bool
    {
        return [] !== array_intersect($this->classes($element), self::DYNAMIC_CLASSES) || \in_array($element->localName, ['style', 'script', 'form', 'noscript', 'template'], true);
    }

    /**
     * @internal used by ConversionRun
     *
     * @return list<string>
     */
    public function classes(\Dom\Element $element): array
    {
        return array_values(array_filter(explode(' ', (string) $element->getAttribute('class'))));
    }
}
