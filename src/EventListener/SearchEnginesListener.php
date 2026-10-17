<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Seo\SearchEngines;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * On a staging copy of the site (SEARCH_ENGINES_ALLOWED=0), every response tells search engines to
 * neither index it nor follow its links: pages, but also the media and documents of the library,
 * which carry no meta tag (doc/seo.md).
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class SearchEnginesListener
{
    public function __construct(
        private SearchEngines $searchEngines,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if ($event->isMainRequest() && !$this->searchEngines->areAllowed()) {
            $event->getResponse()->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
    }
}
