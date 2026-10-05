<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Visibility;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

/**
 * Fields of a RestrictedContentInterface entity: who may see it (public, logged-in users, groups).
 */
final class VisibilityFields
{
    /**
     * @return iterable<FieldInterface>
     */
    public static function create(): iterable
    {
        yield FormField::addFieldset('Visibilité', 'fa fa-lock');
        yield ChoiceField::new('visibility', 'Visible par')
            ->setChoices(array_combine(array_map(static fn (Visibility $visibility): string => $visibility->label(), Visibility::cases()), Visibility::cases()))
            ->formatValue(static fn (mixed $value): mixed => $value instanceof Visibility ? $value->label() : $value)
            ->renderAsBadges([
                Visibility::Public->value => 'success',
                Visibility::Authenticated->value => 'info',
                Visibility::Groups->value => 'warning',
            ]);
        yield AssociationField::new('allowedGroups', 'Groupes autorisés')
            ->setFormTypeOptions(['by_reference' => false, 'expanded' => true])
            ->setHelp('Utilisé seulement avec « Groupes choisis ».')
            ->hideOnIndex();
    }
}
