<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use App\Security\AccessLabel;
use App\Security\Voter\ContentVoter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Builds the menu tree a reader may see. A link is hidden when its own visibility excludes the reader
 * (CONTENT_VIEW), when its target page is not published, or when that page is private to others
 * (CONTENT_LIST); a link to an announced page stays visible with an access tag and leads to the
 * "Contenu réservé" page. A heading without visible links is dropped. Used in templates through
 * `menu('main')`.
 */
final class MenuBuilder implements ResetInterface
{
    /** @var array<string, list<MenuNode>> */
    private array $built = [];

    public function __construct(
        private readonly MenuItemRepository $items,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return list<MenuNode>
     */
    public function build(MenuLocation $location): array
    {
        if (isset($this->built[$location->value])) {
            return $this->built[$location->value];
        }

        $byParent = [];
        foreach ($this->items->findByLocation($location) as $item) {
            $byParent[$item->getParent()?->getId() ?? 0][] = $item;
        }

        $currentPath = $this->requestStack->getCurrentRequest()?->getPathInfo() ?? '';

        return $this->built[$location->value] = $this->nodes($byParent, 0, $currentPath);
    }

    public function reset(): void
    {
        $this->built = [];
    }

    /**
     * @param array<int, list<MenuItem>> $byParent
     *
     * @return list<MenuNode>
     */
    private function nodes(array $byParent, int $parentId, string $currentPath): array
    {
        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $item) {
            if (!$this->isVisible($item)) {
                continue;
            }

            $children = $this->nodes($byParent, (int) $item->getId(), $currentPath);
            $href = $this->href($item);
            if (null === $href && [] === $children) {
                continue; // heading whose links are all hidden
            }

            $access = $this->accessLabel($item);
            $current = null !== $href && !str_contains($href, '#') && rtrim($href, '/') === rtrim($currentPath, '/');
            $nodes[] = new MenuNode(
                $item->getLabel(),
                $href,
                $item->isExternal(),
                $this->isActive($href, $currentPath) || array_any($children, static fn (MenuNode $child): bool => $child->active),
                null !== $access,
                $children,
                $item->getDescription(),
                $access,
                $current,
            );
        }

        return $nodes;
    }

    /**
     * The link's own visibility, then its page: published and listed for the reader (an announced
     * page keeps its link with an access tag, a private one takes it away).
     */
    private function isVisible(MenuItem $item): bool
    {
        $page = $item->getPage();

        return $this->authorizationChecker->isGranted(ContentVoter::VIEW, $item)
            && (null === $page || ($page->isPublished() && $this->authorizationChecker->isGranted(ContentVoter::LIST, $page)));
    }

    /**
     * Access tag of a link to reserved content (its page's audience, or its own).
     */
    private function accessLabel(MenuItem $item): ?string
    {
        foreach ([$item->getPage(), $item] as $content) {
            $label = null !== $content ? AccessLabel::of($content) : null;
            if (null !== $label) {
                return $label;
            }
        }

        return null;
    }

    private function href(MenuItem $item): ?string
    {
        if (null !== $item->getPage()) {
            return $this->urlGenerator->generate('app_page_show', ['path' => $item->getPage()->getPath()]);
        }

        return $item->getUrl();
    }

    private function isActive(?string $href, string $currentPath): bool
    {
        if (null === $href || str_contains($href, '#') || str_contains($href, '://')) {
            return false;
        }

        $path = rtrim($href, '/');

        return '' === $path ? '/' === $currentPath : ($currentPath === $path || str_starts_with($currentPath, $path.'/'));
    }
}
