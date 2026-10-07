<?php

declare(strict_types=1);

namespace App\WordPress;

use App\Content\Block\BlockFactory;
use App\Entity\Category;
use App\Entity\Group;
use App\Entity\Media;
use App\Entity\Page;
use App\Entity\Post;
use App\Entity\Redirect;
use App\Media\MediaStorage;
use App\Repository\CategoryRepository;
use App\Repository\GroupRepository;
use App\Repository\MediaRepository;
use App\Repository\PageRepository;
use App\Repository\PostRepository;
use App\Repository\RedirectRepository;
use App\Security\Visibility;
use App\Twig\MediaExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Imports the content of the WordPress site (app:import-wordpress, doc/wordpress-import.md):
 * categories, pages at their place in the new tree, posts, the files they use, and the redirects of
 * the old addresses. config/wordpress/import.yaml says where each page goes.
 *
 * An import can be run again: content is found by its WordPress id (pages also by their new path,
 * to fill the pages that already exist) and updated, files by their address.
 *
 * Content the anonymous API hides ("Contenu restreint") is reserved to the groups of
 * `restricted_groups`; reading it needs the application password of WORDPRESS_USER.
 */
final class WordPressImporter
{
    private const string RESTRICTED_TITLE = 'Contenu restreint';

    /** @var array{pages: array<string, string|array{redirect: string}|'skip'>, categories: array<string, array{name: string, slug: string}>, restricted_groups: list<string>} */
    private array $config;

    /** @var array<string, string> old path → new path or URL, for links and redirects */
    private array $links = [];

    /** @var array<string, Media> imported files by address */
    private array $media = [];

    private ImportReport $report;

    private bool $dryRun = false;

    /** @var list<Group> */
    private array $restrictedGroups = [];

    /** @var array<int, string>|null WordPress category slugs by id */
    private ?array $categorySlugs = null;

    public function __construct(
        private readonly WordPressClient $client,
        private readonly HtmlConverter $converter,
        private readonly BlockFactory $blockFactory,
        private readonly MediaStorage $storage,
        private readonly MediaExtension $mediaUrls,
        private readonly EntityManagerInterface $entityManager,
        private readonly PageRepository $pages,
        private readonly PostRepository $posts,
        private readonly CategoryRepository $categories,
        private readonly MediaRepository $mediaRepository,
        private readonly RedirectRepository $redirects,
        private readonly GroupRepository $groups,
        #[Autowire('%kernel.project_dir%/config/wordpress/import.yaml')]
        private readonly string $configFile,
    ) {
    }

    /**
     * @param 'pages'|'posts'|null $only
     * @param bool                 $dryRun reads and converts everything, downloads no file and saves nothing
     */
    public function import(?string $only = null, bool $dryRun = false): ImportReport
    {
        $this->report = new ImportReport();
        $this->dryRun = $dryRun;
        $this->config = Yaml::parseFile($this->configFile);
        $this->restrictedGroups = array_values(array_filter(array_map($this->groups->findOneByCode(...), $this->config['restricted_groups'])));
        if (!$this->client->hasCredentials()) {
            $this->report->warn('WORDPRESS_USER et WORDPRESS_APP_PASSWORD ne sont pas définis : les contenus réservés ne sont pas importés.');
        }

        $pages = $this->fetch('pages', 'id,slug,link,parent,menu_order,title,content,excerpt,date,modified');
        $posts = $this->fetch('posts', 'id,slug,link,title,content,excerpt,date,modified,categories,sticky,featured_media');
        $this->checkPageMap($pages);
        $this->mapLinks($pages, $posts);

        $this->entityManager->beginTransaction();
        try {
            if ('posts' !== $only) {
                $this->importPages($pages);
            }
            if ('pages' !== $only) {
                $this->importPosts($posts);
            }
            $this->saveRedirects();
            $this->entityManager->flush();
            $dryRun ? $this->entityManager->rollback() : $this->entityManager->commit();
        } catch (\Throwable $exception) {
            $this->entityManager->rollback();
            throw $exception;
        }

        return $this->report;
    }

    /**
     * Items of a collection with their visibility: those the anonymous API calls "Contenu restreint"
     * are reserved; without credentials their content is unknown and they are left out.
     *
     * @return list<array<string, mixed>>
     */
    private function fetch(string $collection, string $fields): array
    {
        $restricted = [];
        foreach ($this->client->all($collection, ['_fields' => 'id,title'], authenticated: false) as $item) {
            if (self::RESTRICTED_TITLE === self::decode($item['title']['rendered'] ?? '')) {
                $restricted[(int) $item['id']] = true;
            }
        }

        $items = [];
        foreach ($this->client->all($collection, ['_fields' => $fields]) as $item) {
            $item['restricted'] = isset($restricted[(int) $item['id']]);
            if ($item['restricted'] && self::RESTRICTED_TITLE === self::decode($item['title']['rendered'] ?? '')) {
                $this->report->warn(\sprintf('%s non lu (réservé) : %s', 'pages' === $collection ? 'Page' : 'Article', self::path((string) $item['link'])));
                $item['unreadable'] = true;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $pages
     */
    private function checkPageMap(array $pages): void
    {
        $unknown = array_filter(array_map(fn (array $page): ?string => \array_key_exists(self::path((string) $page['link']), $this->config['pages']) ? null : self::path((string) $page['link']), $pages));
        if ([] !== $unknown) {
            throw new \RuntimeException(\sprintf('Pages absentes de %s : %s', basename($this->configFile), implode(', ', $unknown)));
        }
    }

    /**
     * Where each old address now leads: pages (mapped or redirected), posts (/2025/03/19/slug →
     * /actualites/slug) and categories (/category/club → /actualites?categorie=vie-du-club).
     *
     * @param list<array<string, mixed>> $pages
     * @param list<array<string, mixed>> $posts
     */
    private function mapLinks(array $pages, array $posts): void
    {
        foreach ($this->config['pages'] as $old => $target) {
            if (\is_array($target)) {
                $this->links['/'.$old] = $target['redirect'];
            } elseif ('skip' !== $target) {
                $this->links['/'.$old] = '/'.$target;
            }
        }
        foreach ($posts as $post) {
            $this->links[self::path((string) $post['link'], true)] = '/actualites/'.$post['slug'];
        }
        foreach ($this->config['categories'] as $old => $category) {
            $this->links['/category/'.$old] = '/actualites?categorie='.$category['slug'];
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function importPages(array $items): void
    {
        $byPath = [];
        foreach ($items as $item) {
            $target = $this->config['pages'][self::path((string) $item['link'])];
            if (\is_string($target) && 'skip' !== $target && !isset($item['unreadable'])) {
                $byPath[$target] = $item;
            }
        }
        // Parents first
        uksort($byPath, static fn (string $a, string $b): int => substr_count($a, '/') <=> substr_count($b, '/') ?: strcmp($a, $b));

        foreach ($byPath as $path => $item) {
            $page = $this->pages->findOneBy(['wordpressId' => $item['id']]) ?? $this->pages->findOneByPath($path);
            $segments = explode('/', $path);
            $slug = array_pop($segments);
            $parent = [] === $segments ? null : $this->pages->findOneByPath(implode('/', $segments));
            if ([] !== $segments && null === $parent) {
                $this->report->warn(\sprintf('Page %s : page parente %s introuvable, page non importée.', $path, implode('/', $segments)));
                continue;
            }

            // A page of the new tree keeps its title (menus, breadcrumbs); a new one takes WordPress's
            $new = null === $page;
            $page ??= (new Page(self::decode($item['title']['rendered']), $slug))->setWordpressId((int) $item['id']);
            $context = $this->context('page '.$path, (bool) $item['restricted']);
            $page->setWordpressId((int) $item['id'])
                ->setSlug($slug)
                ->setParent($parent)
                ->setPosition(intdiv((int) $item['menu_order'], 10))
                ->setExcerpt(self::excerpt($item) ?: $page->getExcerpt())
                ->setBody($this->blockFactory->serializeAll($this->converter->convert((string) $item['content']['rendered'], $context)))
                ->setPublishedAt(self::date((string) $item['date']))
                ->setUpdatedAt(self::date((string) $item['modified']));
            $this->restrict($page, (bool) $item['restricted']);
            $this->report->warn(...$context->warnings());

            $this->entityManager->persist($page);
            // Children look their parent up by path
            $this->entityManager->flush();
            $this->report->count($new ? 'Pages créées' : 'Pages mises à jour');
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function importPosts(array $items): void
    {
        foreach ($items as $item) {
            if (isset($item['unreadable'])) {
                continue;
            }
            $post = $this->posts->findOneBy(['wordpressId' => $item['id']]);
            $new = null === $post;
            $slug = (string) $item['slug'];
            $other = $this->posts->findOneBy(['slug' => $slug]);
            if (null !== $other && $other !== $post) {
                $slug .= '-'.$item['id'];
                $this->report->warn(\sprintf('Article %s : adresse déjà prise, importé sous /actualites/%s.', $item['slug'], $slug));
                $this->links[self::path((string) $item['link'], true)] = '/actualites/'.$slug;
            }

            $post ??= new Post('', $slug);
            $context = $this->context('article '.$item['slug'], (bool) $item['restricted']);
            $title = self::decode($item['title']['rendered']);
            $post->setWordpressId((int) $item['id'])
                ->setTitle($title)
                ->setSlug($slug)
                ->setExcerpt(self::excerpt($item) ?: self::firstParagraph($item) ?: $title)
                ->setCategory($this->category($item['categories'][0] ?? null))
                ->setFeatured((bool) $item['sticky'])
                ->setBody($this->blockFactory->serializeAll($this->converter->convert((string) $item['content']['rendered'], $context)))
                ->setPublishedAt(self::date((string) $item['date']))
                ->setUpdatedAt(self::date((string) $item['modified']));
            if (0 !== (int) $item['featured_media']) {
                $cover = $this->client->get('media', (int) $item['featured_media']);
                $media = null !== $cover ? $this->importMedia((string) $cover['source_url'], $cover['alt_text'] ?? null, (bool) $item['restricted']) : null;
                // A dry run gives unsaved placeholders: no cover then
                $post->setCover(null !== $media?->getId() ? $media : null);
            }
            $this->restrict($post, (bool) $item['restricted']);
            $this->report->warn(...$context->warnings());

            $this->entityManager->persist($post);
            $this->report->count($new ? 'Articles créés' : 'Articles mis à jour');
        }
    }

    private function category(mixed $wordpressId): Category
    {
        $this->categorySlugs ??= array_column($this->client->all('categories', ['_fields' => 'id,slug']), 'slug', 'id');
        $config = $this->config['categories'][$this->categorySlugs[$wordpressId] ?? 'uncategorized'] ?? $this->config['categories']['uncategorized'];

        $category = $this->categories->findOneBy(['slug' => $config['slug']]);
        if (null === $category) {
            $category = new Category($config['name'], $config['slug']);
            $this->entityManager->persist($category);
            $this->entityManager->flush();
            $this->report->count('Catégories créées');
        }

        return $category;
    }

    /**
     * @param Page|Post $content
     */
    private function restrict(object $content, bool $restricted): void
    {
        if (!$restricted) {
            $content->setVisibility(Visibility::Public)->setAnnounced(true);

            return;
        }
        // Committee content: hidden from the other readers, not even listed with a padlock
        $content->setVisibility(Visibility::Groups)->setAnnounced(false);
        foreach ($this->restrictedGroups as $group) {
            $content->addAllowedGroup($group);
        }
    }

    private function context(string $label, bool $restricted): ConversionContext
    {
        return new ConversionContext(
            fn (string $url): string => $this->link($url, $restricted),
            // In a dry run no file is downloaded: blocks get a placeholder id
            fn (string $url, ?string $alt): ?int => $this->dryRun ? (int) (bool) $this->importMedia($url, $alt, $restricted) : $this->importMedia($url, $alt, $restricted)?->getId(),
            $label,
        );
    }

    /** A link of the old site leads to the new place of its page, post or file; others are kept. */
    private function link(string $url, bool $restricted): string
    {
        $host = parse_url($this->client->baseUrl(), \PHP_URL_HOST);
        $parts = parse_url($url);
        if (false === $parts || (isset($parts['host']) && $parts['host'] !== $host) || (!isset($parts['host']) && !str_starts_with($url, '/'))) {
            return $url;
        }

        $path = (string) ($parts['path'] ?? '/');
        if (str_starts_with($path, '/wp-content/uploads/')) {
            $media = $this->importMedia($this->client->baseUrl().$path, null, $restricted);

            return null !== $media && null !== $media->getId() ? $this->mediaUrls->mediaUrl($media) : $url;
        }

        $target = $this->links[Redirect::normalize($path)] ?? null;
        if (null === $target) {
            return isset($parts['host']) ? $path.(isset($parts['fragment']) ? '#'.$parts['fragment'] : '') : $url;
        }

        return $target.(isset($parts['fragment']) && !str_contains($target, '#') ? '#'.$parts['fragment'] : '');
    }

    /**
     * Imports a file of the site once (by address); a resized picture ("photo-1024x576.jpg") is
     * replaced by its original. A file first used by reserved content is reserved too, and becomes
     * public as soon as public content uses it. In a dry run, nothing is downloaded.
     */
    private function importMedia(string $url, ?string $alt, bool $restricted): ?Media
    {
        $url = strtok($url, '?#') ?: $url;
        $original = (string) preg_replace('/-\d+x\d+(?=\.[a-z0-9]+$)/i', '', $url);
        $media = $this->media[$original] ?? $this->mediaRepository->findOneBy(['sourceUrl' => $original]);
        if (null === $media && $this->dryRun) {
            $this->report->count('Fichiers à télécharger');
            $this->media[$original] = new Media('', basename($original), '', 0);

            return $this->media[$original];
        }

        if (null === $media) {
            try {
                $file = $this->client->download($original);
            } catch (\Throwable) {
                try {
                    $file = $this->client->download($url);
                } catch (\Throwable $exception) {
                    $this->report->warn(\sprintf('Fichier non téléchargé : %s (%s)', $url, $exception->getMessage()));

                    return null;
                }
            }
            $media = $this->storage->storeCopy($file, rawurldecode(basename((string) parse_url($original, \PHP_URL_PATH))));
            @unlink($file);
            $media->setSourceUrl($original)->setAlt('' !== (string) $alt ? $alt : null);
            if ($restricted) {
                $media->setVisibility(Visibility::Groups);
                foreach ($this->restrictedGroups as $group) {
                    $media->addAllowedGroup($group);
                }
            }
            $this->entityManager->persist($media);
            $this->entityManager->flush();
            $this->report->count('Fichiers importés');
        } elseif (!$restricted && Visibility::Public !== $media->getVisibility()) {
            $media->setVisibility(Visibility::Public);
        }

        $this->media[$original] = $media;
        if (null !== $media->getId()) {
            $this->links[Redirect::normalize((string) parse_url($original, \PHP_URL_PATH))] = $this->mediaUrls->mediaUrl($media);
        }

        return $media;
    }

    /** Old addresses that changed: pages moved or removed, posts, categories and files. */
    private function saveRedirects(): void
    {
        foreach ($this->links as $source => $target) {
            if ($source === Redirect::normalize($target)) {
                continue;
            }
            $redirect = $this->redirects->findOneBy(['source' => Redirect::normalize($source)]);
            if (null === $redirect) {
                $this->entityManager->persist(new Redirect($source, $target));
                $this->report->count('Redirections créées');
            } elseif ($redirect->getTarget() !== $target) {
                $redirect->setTarget($target);
                $this->report->count('Redirections mises à jour');
            }
        }
    }

    /**
     * Path of an address of the site without slashes ("le-club/statuts"), or with its leading slash
     * and no trailing one ("/2025/03/19/slug").
     */
    private static function path(string $link, bool $absolute = false): string
    {
        $path = trim((string) parse_url($link, \PHP_URL_PATH), '/');

        return $absolute ? '/'.$path : $path;
    }

    private static function decode(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }

    /**
     * The excerpt written in WordPress, as plain text, or an empty string. Without one, WordPress
     * makes it from the start of the content: that one is left out.
     *
     * @param array<string, mixed> $item
     */
    private static function excerpt(array $item): string
    {
        $html = (string) ($item['excerpt']['rendered'] ?? '');
        preg_match('~<p>(.*?)</p>~s', $html, $matches);
        $excerpt = self::decode($matches[1] ?? $html);
        $start = preg_replace('/\s+/u', ' ', self::decode((string) ($item['content']['rendered'] ?? '')));
        $significant = rtrim(preg_replace('/\s*(\[…\]|\[\.\.\.\]|…)$/u', '', (string) preg_replace('/\s+/u', ' ', $excerpt)) ?? '');
        if ('' === $significant || str_starts_with((string) $start, mb_substr($significant, 0, 60))) {
            return '';
        }

        return mb_strimwidth($excerpt, 0, 300, '…');
    }

    /**
     * Lead of a post without written excerpt: the first paragraph of its content.
     *
     * @param array<string, mixed> $item
     */
    private static function firstParagraph(array $item): string
    {
        preg_match('~<p[^>]*>(.*?)</p>~s', (string) ($item['content']['rendered'] ?? ''), $matches);

        return mb_strimwidth(self::decode($matches[1] ?? ''), 0, 300, '…');
    }

    /** WordPress dates are in the site's time zone (Europe/Paris), as ours. */
    private static function date(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }
}
