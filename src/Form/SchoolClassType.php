<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\SchoolClass;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A class of a teacher (doc/teacher-space.md): its name, its school, and whether its link still lets
 * pupils join (`with_open`, once the class exists).
 *
 * @extends AbstractType<SchoolClass>
 */
final class SchoolClassType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la classe',
                'help' => 'Par exemple « BIA 2026-2027 ». Les élèves le voient en rejoignant la classe.',
                'attr' => ['maxlength' => 100],
            ])
            ->add('school', TextType::class, [
                'label' => 'Établissement',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ]);
        if ($options['with_open']) {
            $builder->add('open', CheckboxType::class, [
                'label' => 'Le lien permet de rejoindre la classe',
                'required' => false,
                'help' => 'Décochez une fois tous les élèves inscrits : le lien ne servira plus à personne.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SchoolClass::class, 'with_open' => false]);
        $resolver->setAllowedTypes('with_open', 'bool');
    }
}
