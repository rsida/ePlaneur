<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * "Référencement" fields of the post and page editors (App\Content\SearchSettings). All optional:
 * left empty, the title, the lead and the cover are used.
 */
final class SearchFields
{
    /**
     * @param FormBuilderInterface<mixed> $builder
     */
    public static function add(FormBuilderInterface $builder, string $imageFallback): void
    {
        $builder
            ->add('seoTitle', TextType::class, [
                'label' => 'Titre pour les moteurs',
                'required' => false,
                'help' => 'Vide : le titre. Le nom du club est ajouté à la suite : 45 caractères au plus pour que Google l’affiche en entier.',
                'attr' => ['maxlength' => 70],
            ])
            ->add('seoDescription', TextareaType::class, [
                'label' => 'Description pour les moteurs',
                'required' => false,
                'help' => 'Vide : le chapô. Environ 155 caractères : ce que Google, les IA et les aperçus de lien affichent sous le titre.',
                'attr' => ['maxlength' => 300, 'rows' => 3],
            ])
            ->add('seoImage', MediaPickerType::class, ['label' => 'Image des aperçus de lien', 'help' => 'Vide : '.$imageFallback.'. Format paysage, 1200 × 630 px idéalement.'])
            ->add('noIndex', CheckboxType::class, [
                'label' => 'Ne pas référencer',
                'required' => false,
                'label_attr' => ['class' => 'checkbox-switch'],
                'help' => 'Hors des moteurs de recherche, même ouvert à tout le monde.',
            ]);
    }
}
