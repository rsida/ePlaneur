<?php

declare(strict_types=1);

namespace App\WordPress;

use App\Content\Block as B;

/**
 * One conversion of WordPress HTML into blocks (see HtmlConverter for the correspondence): walks the
 * elements in order, merging consecutive paragraphs, files and spoilers into one block each.
 *
 * @internal used by HtmlConverter
 */
final class ConversionRun
{
    /** @var list<B\BlockInterface> */
    private array $blocks = [];

    /** @var list<string> HTML of paragraphs and lists waiting to form a text block */
    private array $text = [];

    /** @var list<B\DownloadItem> */
    private array $downloads = [];

    /** @var list<B\FaqItem> */
    private array $questions = [];

    /** @var list<B\BlockInterface> blocks found inside spoilers, placed after the questions */
    private array $afterQuestions = [];

    public function __construct(
        private readonly HtmlConverter $converter,
        private readonly ConversionContext $context,
    ) {
    }

    public function children(\Dom\Node $parent): void
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof \Dom\Element) {
                $this->element($node);
            } elseif ($node instanceof \Dom\Text && '' !== trim($node->textContent ?? '')) {
                $this->text[] = '<p>'.htmlspecialchars(trim($node->textContent ?? '')).'</p>';
            }
        }
    }

    /**
     * @return list<B\BlockInterface>
     */
    public function finish(): array
    {
        $this->flush();

        return $this->blocks;
    }

    private function element(\Dom\Element $element): void
    {
        $tag = $element->localName;
        $classes = $this->converter->classes($element);
        $text = self::text($element);

        if ($this->converter->isDynamic($element)) {
            $this->context->warn(\sprintf('élément dynamique ignoré (%s)', trim($tag.'.'.implode('.', $classes), '.')));

            return;
        }

        if (\in_array($tag, ['h1', 'h2'], true)) {
            if ('' !== $text) {
                $this->add(new B\SectionBlock($text));
            }
        } elseif (\in_array($tag, ['h3', 'h4', 'h5', 'h6'], true)) {
            if ('' !== $text) {
                $this->add(new B\HeadingBlock($text, 'h3' === $tag ? 'lg' : 'md'));
            }
        } elseif ('p' === $tag) {
            $this->paragraph($element, $text);
        } elseif (\in_array($tag, ['ul', 'ol'], true)) {
            $this->text[] = $element->outerHTML;
        } elseif ('pre' === $tag) {
            $this->text[] = '<p><code>'.nl2br(htmlspecialchars((string) $element->textContent)).'</code></p>';
        } elseif ('hr' === $tag) {
            $this->flush();
        } elseif ('blockquote' === $tag) {
            $this->quote($element);
        } elseif ('table' === $tag || \in_array('wp-block-table', $classes, true)) {
            $this->table($element);
        } elseif ('img' === $tag) {
            $this->image($element, null);
        } elseif (\in_array('wp-block-image', $classes, true)) {
            $this->imageFigure($element);
        } elseif (\in_array('wp-block-file', $classes, true)) {
            $this->file($element);
        } elseif (\in_array('wp-block-embed', $classes, true) || 'iframe' === $tag) {
            $this->embed($element);
        } elseif ([] !== array_intersect(['wp-block-video', 'wp-block-audio'], $classes) || \in_array($tag, ['video', 'audio'], true)) {
            $this->recording($element);
        } elseif (\in_array('su-spoiler', $classes, true) || 'details' === $tag) {
            $this->spoiler($element);
        } elseif (\in_array('wp-block-buttons', $classes, true)) {
            $this->buttons($element);
        } elseif ($this->converter->isTransparent($element)) {
            $this->children($element);
        } elseif (\in_array($tag, ['strong', 'b', 'em', 'i', 'a', 'code'], true)) {
            $this->text[] = '<p>'.$element->outerHTML.'</p>';
        } elseif ('br' !== $tag) {
            $this->unknown($element, $text);
        }
    }

    private function paragraph(\Dom\Element $element, string $text): void
    {
        $images = $element->querySelectorAll('img');
        if ('' === $text && $images->length > 0) {
            foreach ($images as $image) {
                $this->image($image, null);
            }

            return;
        }
        if ('' !== $text) {
            $this->text[] = '<p>'.$element->innerHTML.'</p>';
        }
    }

    private function quote(\Dom\Element $element): void
    {
        $cite = $element->querySelector('cite');
        $attribution = null !== $cite ? self::text($cite) : null;
        $cite?->remove();
        $text = self::text($element);
        if ('' !== $text) {
            $this->add(new B\QuoteBlock($text, '' !== $attribution ? $attribution : null));
        }
    }

    private function table(\Dom\Element $element): void
    {
        $rows = [];
        foreach ($element->querySelectorAll('tr') as $row) {
            $cells = [];
            foreach ($row->querySelectorAll('th, td') as $cell) {
                $cells[] = self::text($cell);
            }
            if ([] !== $cells) {
                $rows[] = $cells;
            }
        }
        if ([] === $rows) {
            return;
        }
        // The first row gives the column titles (a header row when there is one)
        $headers = array_shift($rows);
        $caption = $element->querySelector('figcaption, caption');
        $this->add(new B\TableBlock($headers, $rows, null !== $caption ? self::text($caption) : null));
    }

    private function imageFigure(\Dom\Element $element): void
    {
        $image = $element->querySelector('img');
        if (null === $image) {
            return;
        }
        $caption = $element->querySelector('figcaption');
        $this->image($image, null !== $caption ? self::text($caption) : null);
    }

    private function image(\Dom\Element $image, ?string $caption): void
    {
        $source = (string) $image->getAttribute('src');
        $mediaId = '' !== $source ? $this->context->media($source, $image->getAttribute('alt')) : null;
        if (null === $mediaId) {
            $this->context->warn(\sprintf('image non importée (%s)', $source));

            return;
        }
        $this->add(new B\ImageBlock($mediaId, '' !== $caption ? $caption : null));
    }

    private function file(\Dom\Element $element): void
    {
        $link = $element->querySelector('a[href]:not(.wp-block-file__button)') ?? $element->querySelector('a[href]');
        $object = $element->querySelector('object[data]');
        $url = (string) ($object?->getAttribute('data') ?? $link?->getAttribute('href'));
        $this->download($url, null !== $link ? self::text($link) : basename($url));
    }

    /** Uploaded video or audio file: offered for download (the site does not host players). */
    private function recording(\Dom\Element $element): void
    {
        $player = \in_array($element->localName, ['video', 'audio'], true) ? $element : $element->querySelector('video, audio');
        $source = (string) ($player?->getAttribute('src') ?: $player?->querySelector('source')?->getAttribute('src'));
        $caption = $element->querySelector('figcaption');
        $kind = 'audio' === $player?->localName ? 'Enregistrement audio' : 'Vidéo';
        $this->download($source, null !== $caption ? self::text($caption) : $kind.' : '.basename($source));
    }

    private function download(string $url, string $title): void
    {
        $mediaId = '' !== $url ? $this->context->media($url, null) : null;
        if (null === $mediaId) {
            $this->context->warn(\sprintf('fichier non importé (%s)', $url));

            return;
        }
        if ([] === $this->downloads) {
            $this->flush();
        }
        $this->downloads[] = new B\DownloadItem($mediaId, '' !== $title ? $title : basename($url));
    }

    private function embed(\Dom\Element $element): void
    {
        $iframe = 'iframe' === $element->localName ? $element : $element->querySelector('iframe');
        $url = (string) ($iframe?->getAttribute('src') ?: self::text($element));
        $video = new B\VideoBlock(trim($url));
        if (null !== $video->embedUrl()) {
            $this->add($video);
        } elseif ('' !== trim($url)) {
            $this->add(new B\LinksBlock([new B\LinkItem(trim($url), $this->context->link(trim($url)))], 'arrows'));
        }
    }

    private function spoiler(\Dom\Element $element): void
    {
        $title = $element->querySelector('.su-spoiler-title, summary');
        $content = $element->querySelector('.su-spoiler-content') ?? $element;
        $question = null !== $title ? self::text($title) : '';
        $title?->remove();

        $inner = new self($this->converter, $this->context);
        $inner->children($content);
        $blocks = $inner->finish();

        // A spoiler with headings, pictures or files is not a question: its title becomes a heading
        // and its content follows, open
        if ([] !== array_filter($blocks, static fn (B\BlockInterface $block): bool => !$block instanceof B\TextBlock && !$block instanceof B\DownloadsBlock)) {
            if ('' !== $question) {
                $this->add(new B\HeadingBlock($question, 'md'));
            }
            foreach ($blocks as $block) {
                $this->add($block);
            }

            return;
        }

        // Otherwise its text is the answer, and its files follow the questions
        $answer = '';
        foreach ($blocks as $block) {
            if ($block instanceof B\TextBlock) {
                $answer .= $block->html;
            } else {
                $this->afterQuestions[] = $block;
            }
        }

        if ([] === $this->questions) {
            $this->flush();
        }
        $this->questions[] = new B\FaqItem($question, $this->converter->richText($answer));
    }

    private function buttons(\Dom\Element $element): void
    {
        $links = [];
        foreach ($element->querySelectorAll('a[href]') as $link) {
            $links[] = new B\LinkItem(self::text($link), (string) $link->getAttribute('href'));
        }
        if ([] !== $links) {
            $this->add(new B\LinksBlock($links, 'arrows'));
        }
    }

    private function unknown(\Dom\Element $element, string $text): void
    {
        $this->context->warn(\sprintf('élément non reconnu <%s class="%s">, gardé comme texte', $element->localName, $element->getAttribute('class')));
        if ('' !== $text) {
            $this->text[] = '<p>'.htmlspecialchars($text).'</p>';
        }
    }

    private function add(B\BlockInterface $block): void
    {
        $this->flush();
        $this->blocks[] = $block;
    }

    /** Ends the text, files and questions being gathered. */
    private function flush(): void
    {
        if ([] !== $this->text) {
            $html = $this->converter->richText(implode('', $this->text));
            if ('' !== $html) {
                $this->blocks[] = new B\TextBlock($html);
            }
            $this->text = [];
        }
        if ([] !== $this->downloads) {
            $this->blocks[] = new B\DownloadsBlock($this->downloads);
            $this->downloads = [];
        }
        if ([] !== $this->questions) {
            $this->blocks[] = new B\FaqBlock($this->questions, openFirst: false);
            array_push($this->blocks, ...$this->afterQuestions);
            $this->questions = [];
            $this->afterQuestions = [];
        }
    }

    /** Text of an element, spaces collapsed. */
    private static function text(\Dom\Element $element): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $element->textContent));
    }
}
