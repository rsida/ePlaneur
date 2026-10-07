<?php

declare(strict_types=1);

namespace App\Controller\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NullFilterType;

/**
 * "État" filter of a list of posts or pages, on their publication date: draft (no date), scheduled
 * (date to come) or published (date passed), as shown by admin/field/post_state.html.twig.
 */
final class PublicationStateFilter implements FilterInterface
{
    use FilterTrait;

    public const string DRAFT = 'draft';
    public const string SCHEDULED = 'scheduled';
    public const string PUBLISHED = 'published';

    /** Publication date of the filtered entity. */
    private const string DATE = 'publishedAt';

    /**
     * @param string $propertyName name of the filter in the URL, not a mapped property: the date
     *                             filter of the list already uses "publishedAt"
     */
    public static function new(string $propertyName = 'state', string $label = 'État'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(NullFilterType::class)
            ->setFormTypeOption('choices', ['Brouillon' => self::DRAFT, 'Programmé' => self::SCHEDULED, 'Publié' => self::PUBLISHED]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $date = $filterDataDto->getEntityAlias().'.'.self::DATE;
        $parameter = $filterDataDto->getParameterName();

        match ($filterDataDto->getValue()) {
            self::DRAFT => $queryBuilder->andWhere($date.' IS NULL'),
            self::SCHEDULED => $queryBuilder->andWhere($date.' > :'.$parameter)->setParameter($parameter, new \DateTimeImmutable()),
            self::PUBLISHED => $queryBuilder->andWhere($date.' <= :'.$parameter)->setParameter($parameter, new \DateTimeImmutable()),
            default => null,
        };
    }
}
