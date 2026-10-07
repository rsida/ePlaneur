<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Group;
use App\Security\Visibility;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Access fields of a restricted content (RestrictedContentInterface): who may open it, and what the
 * others get, "Annoncé" (listed with a padlock, "Contenu réservé" at its address) or "Privé" (listed
 * nowhere, address not found). Shared by the post and page editors and the media upload.
 */
final class AccessFields
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     * @param bool                        $mapped  false for a form without data class (media upload):
     *                                             read the values with $form->get('visibility')...
     *                                             and give defaults here
     */
    public static function add(FormBuilderInterface $builder, bool $mapped = true): void
    {
        $builder
            ->add('visibility', EnumType::class, [
                'label' => 'Ouvert à',
                'class' => Visibility::class,
                'choice_label' => static fn (Visibility $visibility): string => $visibility->label(),
                'expanded' => true,
            ] + ($mapped ? [] : ['data' => Visibility::Public]))
            ->add('allowedGroups', EntityType::class, [
                'label' => 'Groupes autorisés',
                'class' => Group::class,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'help' => 'Avec « Groupes choisis ». Un groupe qui en inclut un autre (Comité inclut Membre) y a accès aussi.',
            ])
            ->add('announced', ChoiceType::class, [
                'label' => 'Pour les autres',
                'expanded' => true,
                'choices' => [
                    'Annoncé : visible partout avec un cadenas, son adresse indique qu’il est réservé' => true,
                    'Privé : invisible pour eux, son adresse est introuvable' => false,
                ],
                'help' => 'Sans effet quand le contenu est ouvert à tout le monde.',
            ] + ($mapped ? [] : ['data' => true]));
    }
}
