<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Repository\RedirectRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Addresses of the old site (WordPress) that no longer exist lead to their new place: a 404 on a GET
 * request is replaced by a permanent redirect when the path is in the `redirect` table (filled by
 * app:import-wordpress). The query string is kept.
 *
 * WordPress addresses end with a slash, which the router would first remove (a second redirect):
 * those are looked up before routing.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onNotFound', priority: 10)]
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onTrailingSlash', priority: 40)]
final readonly class LegacyRedirectListener
{
    public function __construct(
        private RedirectRepository $redirects,
    ) {
    }

    public function onNotFound(ExceptionEvent $event): void
    {
        if ($event->getThrowable() instanceof NotFoundHttpException && $event->isMainRequest()) {
            $this->redirect($event);
        }
    }

    public function onTrailingSlash(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if ($event->isMainRequest() && '/' !== $path && str_ends_with($path, '/')) {
            $this->redirect($event);
        }
    }

    private function redirect(ExceptionEvent|RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request->isMethodSafe()) {
            return;
        }

        $target = $this->redirects->findTarget($request->getPathInfo());
        if (null === $target) {
            return;
        }

        $query = $request->getQueryString();
        if (null !== $query && !str_contains($target, '?')) {
            $target .= '?'.$query;
        }
        $event->setResponse(new RedirectResponse($target, Response::HTTP_MOVED_PERMANENTLY));
    }
}
