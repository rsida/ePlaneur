<?php

declare(strict_types=1);

namespace App\Media;

use App\Entity\Media;
use App\Repository\MediaRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Media referenced by content blocks, loaded in one query per page (preload) then read by id while
 * rendering (Twig `media(id)`).
 */
final class MediaLibrary implements ResetInterface
{
    /** @var array<int, Media|null> */
    private array $loaded = [];

    public function __construct(
        private readonly MediaRepository $repository,
    ) {
    }

    /**
     * @param list<int> $ids
     */
    public function preload(array $ids): void
    {
        $missing = array_values(array_diff(array_unique($ids), array_keys($this->loaded)));
        if ([] === $missing) {
            return;
        }

        $found = $this->repository->findByIds($missing);
        foreach ($missing as $id) {
            $this->loaded[$id] = $found[$id] ?? null;
        }
    }

    public function get(?int $id): ?Media
    {
        if (null === $id) {
            return null;
        }
        $this->preload([$id]);

        return $this->loaded[$id];
    }

    public function reset(): void
    {
        $this->loaded = [];
    }
}
