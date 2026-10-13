<?php

declare(strict_types=1);

namespace App\Seo;

use App\Content\Block\Fact;
use App\Content\Block\FaqBlock;
use App\Content\Block\GlossaryBlock;
use App\Content\PlacedBlock;
use App\Entity\Post;
use App\Twig\MediaExtension;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * schema.org descriptions (JSON-LD) read by search engines and AI answer engines: the club and the
 * site on the home page, each post as an article, the breadcrumb of pages and posts, the questions
 * of FAQ blocks and the terms of glossary blocks. See doc/seo.md.
 */
final readonly class StructuredData
{
    public const string NAME = 'Club ePlaneur';

    /** Declared in prefecture (doc/project/club.md). */
    private const string FOUNDED = '2026-01-26';

    /** Profiles of the club, given as Organization.sameAs. */
    private const array PROFILES = [
        'https://www.facebook.com/groups/eplaneur',
        'https://www.youtube.com/channel/UChgfcBCjvQamBSwaGIn3gSw',
    ];

    private const string DESCRIPTION = 'Association française qui forme au vol à voile sur le simulateur Condor et organise chaque semaine des vols en réseau.';

    public function __construct(
        private UrlGeneratorInterface $urls,
        private UrlHelper $urlHelper,
        private Packages $assets,
        private MediaExtension $media,
    ) {
    }

    /**
     * The club and its site, given once on the home page; the other descriptions refer to them by
     * their @id.
     *
     * @return list<array<string, mixed>>
     */
    public function site(): array
    {
        $home = $this->home();

        return [
            [
                '@type' => 'Organization',
                '@id' => $home.'#organization',
                'name' => self::NAME,
                'alternateName' => 'ePlaneur',
                'url' => $home,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $this->urlHelper->getAbsoluteUrl($this->assets->getUrl('images/brand/logo-512.png')),
                    'width' => 512,
                    'height' => 512,
                ],
                'sameAs' => self::PROFILES,
                'description' => self::DESCRIPTION,
                'foundingDate' => self::FOUNDED,
                'knowsAbout' => ['vol à voile', 'planeur', 'simulateur de vol', 'Condor Soaring'],
                'knowsLanguage' => 'fr',
            ],
            [
                '@type' => 'WebSite',
                '@id' => $home.'#website',
                'name' => self::NAME,
                'url' => $home,
                'inLanguage' => 'fr-FR',
                'publisher' => ['@id' => $home.'#organization'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function article(Post $post, string $title, string $description): array
    {
        $url = $this->urls->generate('app_post_show', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        $picture = $post->getSeoImage() ?? $post->getCover();

        return array_filter([
            '@type' => 'BlogPosting',
            '@id' => $url.'#article',
            'mainEntityOfPage' => $url,
            'headline' => $title,
            'description' => $description,
            'image' => null !== $picture ? $this->urlHelper->getAbsoluteUrl($this->media->imageUrl($picture, 'wide')) : null,
            'datePublished' => $post->getPublishedAt()?->format(\DATE_ATOM),
            'dateModified' => $post->getUpdatedAt()->format(\DATE_ATOM),
            'author' => null !== $post->getAuthor()
                ? ['@type' => 'Person', 'name' => $post->getAuthor()->getDisplayName()]
                : ['@id' => $this->home().'#organization'],
            'publisher' => ['@id' => $this->home().'#organization'],
            'articleSection' => $post->getCategory()?->getName(),
            'keywords' => [] !== $post->getKeywords() ? implode(', ', $post->getKeywords()) : null,
            'inLanguage' => 'fr-FR',
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @param list<array{label: string, href: string}> $crumbs from the home page to the current page
     *
     * @return array<string, mixed>
     */
    public function breadcrumb(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $i): array => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['label'],
                'item' => $this->urlHelper->getAbsoluteUrl($crumb['href']),
            ], $crumbs, array_keys($crumbs)),
        ];
    }

    /**
     * What blocks of a page or a post can tell: one FAQPage with the questions of all its FAQ blocks
     * (answers keep their rich text, which Google accepts), one DefinedTermSet per glossary.
     *
     * @param list<PlacedBlock> ...$zones
     *
     * @return list<array<string, mixed>>
     */
    public function blocks(string $url, array ...$zones): array
    {
        $questions = [];
        $glossaries = [];
        foreach (array_merge(...$zones) as $placed) {
            $block = $placed->block;
            if ($block instanceof FaqBlock) {
                foreach ($block->items as $item) {
                    $questions[] = ['@type' => 'Question', 'name' => $item->question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item->answer]];
                }
            } elseif ($block instanceof GlossaryBlock && [] !== $block->entries) {
                $glossaries[] = array_filter([
                    '@type' => 'DefinedTermSet',
                    'name' => $block->title,
                    'hasDefinedTerm' => array_map(static fn (Fact $entry): array => ['@type' => 'DefinedTerm', 'name' => $entry->label, 'description' => $entry->text], $block->entries),
                ], static fn (mixed $value): bool => null !== $value);
            }
        }

        return [...([] !== $questions ? [['@type' => 'FAQPage', '@id' => $url.'#faq', 'mainEntity' => $questions]] : []), ...$glossaries];
    }

    /**
     * The <script> holding descriptions, as one @graph. Escaped for HTML: no "</script>" can close it.
     *
     * @param list<array<string, mixed>> $graph
     */
    public function script(array $graph): string
    {
        $json = json_encode(['@context' => 'https://schema.org', '@graph' => $graph], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_THROW_ON_ERROR);

        return '<script type="application/ld+json">'.$json.'</script>';
    }

    private function home(): string
    {
        return $this->urls->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
