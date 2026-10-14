<?php

declare(strict_types=1);

namespace App\Seo;

use App\Entity\Page;
use App\Entity\Post;
use App\Repository\GliderRepository;
use App\Repository\PageRepository;
use App\Repository\PostRepository;
use App\Security\Visibility;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Addresses of sitemap.xml: the indexable pages of SearchEngines::INDEXABLE. Pages are listed when
 * online and public, as are all the pages above them; posts when published and public; neither
 * when "Ne pas référencer" is ticked.
 */
final readonly class Sitemap
{
    public function __construct(
        private PageRepository $pages,
        private PostRepository $posts,
        private GliderRepository $gliders,
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return list<array{loc: string, lastmod: ?\DateTimeImmutable}>
     */
    public function entries(): array
    {
        $posts = $this->posts->findIndexable();
        $entries = [
            $this->entry('app_home'),
            $this->entry('app_post_index', [], [] !== $posts ? $posts[0]->getUpdatedAt() : null),
            $this->entry('app_membership'),
            $this->entry('app_contact'),
            $this->entry('app_first_flight'),
            $this->entry('app_glider_index'),
        ];
        foreach ($this->pages() as ['page' => $page]) {
            $entries[] = $this->entry('app_page_show', ['path' => $page->getPath()], $page->getUpdatedAt());
        }
        foreach ($posts as $post) {
            $entries[] = $this->entry('app_post_show', ['slug' => $post->getSlug()], $post->getUpdatedAt());
        }
        foreach ($this->gliders->findBy([], ['name' => 'ASC']) as $glider) {
            $entries[] = $this->entry('app_glider_show', ['name' => $glider->getName()]);
        }

        return $entries;
    }

    /**
     * Indexable pages in tree order, with their depth (0 = section).
     *
     * @return list<array{page: Page, depth: int}>
     */
    public function pages(): array
    {
        return array_values(array_filter(
            $this->pages->findTree(),
            static fn (array $node): bool => $node['page']->isOnline() && !$node['page']->isNoIndex()
                && array_all($node['page']->getLineage(), static fn (Page $above): bool => Visibility::Public === $above->getVisibility()),
        ));
    }

    /**
     * Indexable posts, newest first.
     *
     * @return list<Post>
     */
    public function posts(): array
    {
        return $this->posts->findIndexable();
    }

    /**
     * @param array<string, string> $parameters
     *
     * @return array{loc: string, lastmod: ?\DateTimeImmutable}
     */
    private function entry(string $route, array $parameters = [], ?\DateTimeImmutable $lastmod = null): array
    {
        return ['loc' => $this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL), 'lastmod' => $lastmod];
    }
}
