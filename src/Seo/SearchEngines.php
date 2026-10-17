<?php

declare(strict_types=1);

namespace App\Seo;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What search engines are told about the current page: whether they may index it, and its
 * canonical address. Only the public showcase of the club is indexed; flights, logbooks, sessions
 * and flight plans carry the names of pilots and stay out (decision of 2026-10-13, doc/seo.md).
 *
 * A staging copy of the site sets SEARCH_ENGINES_ALLOWED=0: no page is indexable, every response
 * says noindex (SearchEnginesListener) and robots.txt closes the whole site.
 */
final readonly class SearchEngines
{
    /** Routes whose pages may be indexed; pages and posts also need to be public and published. */
    public const array INDEXABLE = [
        'app_home',
        'app_page_show',
        'app_post_index',
        'app_post_show',
        'app_contact',
        'app_first_flight',
        'app_membership',
        'app_glider_index',
        'app_glider_show',
    ];

    /** Query parameters kept in a canonical address: the category and the page of the news list. */
    private const array QUERY = ['categorie', 'page'];

    public function __construct(
        private RequestStack $requests,
        private UrlGeneratorInterface $urls,
        #[Autowire(env: 'bool:SEARCH_ENGINES_ALLOWED')]
        private bool $allowed,
    ) {
    }

    /** False on a staging copy: search engines must stay out of the whole site. */
    public function areAllowed(): bool
    {
        return $this->allowed;
    }

    public function isIndexable(): bool
    {
        $request = $this->requests->getMainRequest();

        return $this->allowed && null !== $request && \in_array($request->attributes->get('_route'), self::INDEXABLE, true);
    }

    /** Absolute address of the current page without its other query parameters, null outside a route. */
    public function canonical(): ?string
    {
        $request = $this->requests->getMainRequest();
        $route = $request?->attributes->get('_route');
        if (null === $request || !\is_string($route) || str_starts_with($route, '_')) {
            return null;
        }
        // The first page of a list is the list itself
        $query = array_filter(
            array_intersect_key($request->query->all(), array_flip(self::QUERY)),
            static fn (mixed $value, string $name): bool => \is_string($value) && '' !== $value && !('page' === $name && '1' === $value),
            \ARRAY_FILTER_USE_BOTH,
        );

        return $this->urls->generate($route, [...$request->attributes->get('_route_params', []), ...$query], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
