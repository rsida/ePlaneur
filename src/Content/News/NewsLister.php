<?php

declare(strict_types=1);

namespace App\Content\News;

use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Prepares the news list (/actualites): the featured post above the unfiltered list, one page of
 * posts for the filters, and the categories and periods readers can choose from.
 *
 * Published posts are listed when the reader may open them, or when they are reserved but announced
 * to the others: their card then shows the audience with a padlock, and the post page tells readers
 * how to get access. Private posts only appear to their audience.
 */
final readonly class NewsLister
{
    public const int PER_PAGE = 12;

    public function __construct(
        private PostRepository $posts,
        private CategoryRepository $categories,
        private Security $security,
    ) {
    }

    /**
     * @throws NotFoundHttpException for an unknown category or a page beyond the last one
     */
    public function list(NewsFilter $filter): NewsView
    {
        $category = null;
        if (null !== $filter->category) {
            $category = $this->categories->findOneBy(['slug' => $filter->category]) ?? throw new NotFoundHttpException('Catégorie inconnue.');
        }

        $reader = $this->security->getUser();
        $reader = $reader instanceof User ? $reader : null;

        // The featured post leads the unfiltered list and is not repeated in it
        $featured = $filter->isEmpty() ? $this->posts->findFeatured($reader) : null;
        $page = $this->posts->paginateNews($reader, $category, $filter->year, $filter->month(), $filter->page, self::PER_PAGE, $featured);
        $total = \count($page);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($filter->page > $pages) {
            throw new NotFoundHttpException('Page introuvable.');
        }

        $counts = $this->posts->countPublishedByCategory($reader);
        $categories = [];
        foreach ($this->categories->findBy([], ['name' => 'ASC']) as $candidate) {
            if (($counts[(int) $candidate->getId()] ?? 0) > 0) {
                $categories[] = ['category' => $candidate, 'count' => $counts[(int) $candidate->getId()]];
            }
        }

        $periods = [];
        $paris = new \DateTimeZone('Europe/Paris');
        foreach ($this->posts->publishedDates($reader) as $date) {
            $local = $date->setTimezone($paris);
            $periods[(int) $local->format('Y')][(int) $local->format('n')] = (int) $local->format('n');
        }

        return new NewsView(
            $filter,
            $category,
            $featured,
            iterator_to_array($page, false),
            $total + (null !== $featured ? 1 : 0),
            $pages,
            self::PER_PAGE,
            array_sum($counts),
            $categories,
            array_map(static fn (array $months): array => array_values($months), $periods),
        );
    }
}
