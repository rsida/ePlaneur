<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Glider;
use App\Repository\GliderRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Links to the sheets of the gliders, for the names of gliders in results and rankings.
 */
final class GliderExtension
{
    /** @var array<string, true>|null names of the recorded gliders, read once per request */
    private ?array $names = null;

    public function __construct(
        private readonly GliderRepository $gliders,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /** Address of the sheet of a glider, null when it is not recorded. */
    #[AsTwigFunction('glider_url')]
    public function url(?string $name): ?string
    {
        $this->names ??= array_fill_keys(array_map(static fn (Glider $glider): string => $glider->getName(), $this->gliders->findAll()), true);

        return null !== $name && isset($this->names[$name]) ? $this->urls->generate('app_glider_show', ['name' => $name]) : null;
    }
}
