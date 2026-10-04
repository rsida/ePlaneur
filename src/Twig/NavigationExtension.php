<?php

declare(strict_types=1);

namespace App\Twig;

use App\Navigation\MenuBuilder;
use App\Navigation\MenuLocation;
use App\Navigation\MenuNode;
use Twig\Attribute\AsTwigFunction;

/**
 * `menu('main')`: links of a menu the current reader may see.
 */
final readonly class NavigationExtension
{
    public function __construct(
        private MenuBuilder $builder,
    ) {
    }

    /**
     * @return list<MenuNode>
     */
    #[AsTwigFunction('menu')]
    public function menu(string $location): array
    {
        return $this->builder->build(MenuLocation::from($location));
    }
}
