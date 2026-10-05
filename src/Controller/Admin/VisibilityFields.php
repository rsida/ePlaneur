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
        yield FormField::addFieldset('Visibilité', 'lock-keyhole');
        // Visibility is a translatable enum: EasyAdmin lists its cases and shows their French label.
        // Badge variants are keyed by case name; admin.css gives them the colors of the theme.
        yield ChoiceField::new('visibility', 'Visible par')
            ->renderAsBadges([
                Visibility::Public->name => 'secondary',
                Visibility::Authenticated->name => 'info',
                Visibility::Groups->name => 'warning',
            ]);
        yield AssociationField::new('allowedGroups', 'Groupes autorisés')
            ->setFormTypeOptions(['by_reference' => false, 'expanded' => true])
            ->setHelp('Utilisé seulement avec « Groupes choisis ».')
            ->hideOnIndex();
    }
}
