<?php

declare(strict_types=1);

namespace App\Controller\Admin\Filter;

use App\Entity\Membership;
use App\Membership\MembershipStatus;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NullFilterType;
use Symfony\Component\Clock\Clock;

/**
 * "Adhésion" filter of a list of accounts, on their membership of the current year: validated,
 * requested (sent or paid, waiting for the committee) or none. Not a service: the year comes from
 * the global clock (Clock::get()), mocked by the tests.
 */
final class MembershipStateFilter implements FilterInterface
{
    use FilterTrait;

    public const string VALIDATED = 'validated';
    public const string PENDING = 'pending';
    public const string NONE = 'none';

    public static function new(string $propertyName = 'membership', string $label = 'Adhésion de l’année'): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(NullFilterType::class)
            ->setFormTypeOption('choices', ['Validée' => self::VALIDATED, 'En cours' => self::PENDING, 'Aucune' => self::NONE]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $parameter = $filterDataDto->getParameterName();
        $statuses = match ($filterDataDto->getValue()) {
            self::VALIDATED => [MembershipStatus::Validated],
            self::PENDING => [MembershipStatus::Requested, MembershipStatus::Paid],
            self::NONE => [MembershipStatus::Validated, MembershipStatus::Requested, MembershipStatus::Paid],
            default => null,
        };
        if (null === $statuses) {
            return;
        }
        $exists = \sprintf(
            'EXISTS (SELECT m_%1$s.id FROM %2$s m_%1$s JOIN m_%1$s.season s_%1$s WHERE m_%1$s.user = %3$s AND s_%1$s.year = :%1$s_year AND m_%1$s.status IN (:%1$s_statuses))',
            $parameter,
            Membership::class,
            $alias,
        );
        $queryBuilder
            ->andWhere(self::NONE === $filterDataDto->getValue() ? 'NOT '.$exists : $exists)
            ->setParameter($parameter.'_year', (int) Clock::get()->now()->format('Y'))
            ->setParameter($parameter.'_statuses', array_map(static fn (MembershipStatus $status): string => $status->value, $statuses));
    }
}
