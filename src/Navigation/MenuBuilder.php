<?php

declare(strict_types=1);

namespace App\Navigation;

use App\Entity\MenuItem;
use App\Repository\MenuItemRepository;
use App\Security\Visibility;
use App\Security\Voter\ContentVoter;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Builds the menu tree a reader may see. A link is hidden when its own visibility excludes the reader
 * (CONTENT_VIEW) or when its target page is not published; a link to a reserved page stays visible
 * with an access tag (teaser) and leads to the "Contenu réservé" page. A heading without visible
 * links is dropped. Used in templates through `menu('main')`.
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

    private function isVisible(MenuItem $item): bool
    {
        return $this->authorizationChecker->isGranted(ContentVoter::VIEW, $item)
            && (null === $item->getPage() || $item->getPage()->isPublished());
    }

    /**
     * Access tag of a link to reserved content: the groups allowed, or "Connectés".
     */
    private function accessLabel(MenuItem $item): ?string
    {
        foreach ([$item->getPage(), $item] as $content) {
            if (null === $content || Visibility::Public === $content->getVisibility()) {
                continue;
            }
            if (Visibility::Authenticated === $content->getVisibility()) {
                return 'Connectés';
            }
            $names = array_map(static fn ($group): string => $group->getName(), $content->getAllowedGroups()->toArray());

            return implode(' · ', $names) ?: 'Réservé';
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
