<?php

declare(strict_types=1);

namespace App\Content\News;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Filters of the news list, read from its French query string:
 * /actualites?categorie=vie-du-club&annee=2025&mois=10&page=2. The month only applies with a year.
 */
final readonly class NewsFilter
{
    public function __construct(
        #[SerializedName('categorie')]
        #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
        public ?string $category = null,
        #[SerializedName('annee')]
        #[Assert\Range(min: 2000, max: 2100)]
        public ?int $year = null,
        #[SerializedName('mois')]
        #[Assert\Range(min: 1, max: 12)]
        public ?int $month = null,
        #[Assert\Positive]
        public int $page = 1,
    ) {
    }

    /** No category nor period chosen: the featured post is shown above the list. */
    public function isEmpty(): bool
    {
        return null === $this->category && null === $this->year;
    }

    public function month(): ?int
    {
        return null !== $this->year ? $this->month : null;
    }

    /**
     * Query string of a link with some filters changed (back to the first page unless `page` is given).
     *
     * @param array{category?: ?string, year?: ?int, month?: ?int, page?: int} $changes
     *
     * @return array<string, string|int>
     */
    public function query(array $changes = []): array
    {
        $category = \array_key_exists('category', $changes) ? $changes['category'] : $this->category;
        $year = \array_key_exists('year', $changes) ? $changes['year'] : $this->year;
        $month = \array_key_exists('month', $changes) ? $changes['month'] : $this->month();
        $page = $changes['page'] ?? 1;

        return array_filter([
            'categorie' => $category,
            'annee' => $year,
            'mois' => null !== $year ? $month : null,
            'page' => $page > 1 ? $page : null,
        ], static fn (string|int|null $value): bool => null !== $value);
    }
}
