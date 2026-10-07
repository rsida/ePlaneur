<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Visibility;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;

/**
 * Access fields of a RestrictedContentInterface entity: who may open it (public, logged-in users,
 * groups) and, for contents, what the others get (announced or private).
 */
final class VisibilityFields
{
    /** Badge variant of each visibility, keyed by case name; admin.css gives them the colors of the theme. */
    public const array BADGES = [
        Visibility::Public->name => 'secondary',
        Visibility::Authenticated->name => 'info',
        Visibility::Groups->name => 'warning',
    ];

    /**
     * @param bool $announced with the choice "Annoncé" / "Privé" (contents; a menu link outside the
     *                        reader's audience is always hidden)
     *
     * @return iterable<FieldInterface>
     */
    public static function create(bool $announced = true): iterable
    {
        yield FormField::addFieldset('Accès', 'lock-keyhole');
        // Visibility is a translatable enum: EasyAdmin lists its cases and shows their French label.
        yield ChoiceField::new('visibility', 'Ouvert à')->renderAsBadges(self::BADGES);
        yield AssociationField::new('allowedGroups', 'Groupes autorisés')
            ->setFormTypeOptions(['by_reference' => false, 'expanded' => true])
            ->setHelp('Avec « Groupes choisis ». Un groupe qui en inclut un autre (Comité inclut Membre) y a accès aussi.')
            ->hideOnIndex();
        if ($announced) {
            yield ChoiceField::new('announced', 'Pour les autres')
                ->setChoices([
                    'Annoncé : visible partout avec un cadenas' => true,
                    'Privé : invisible et introuvable' => false,
                ])
                ->renderExpanded()
                ->setHelp('Sans effet quand le contenu est ouvert à tout le monde.')
                ->hideOnIndex();
        }
    }
}
