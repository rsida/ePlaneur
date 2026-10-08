<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\FlightComment;
use App\Training\TrainingStep;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Comment on a flight (flight page): the text, the step of the training path it is about, and the
 * moment of the flight shown in the viewer (`at`, filled by the flight-viewer controller), kept when
 * "À cet instant du vol" is ticked.
 *
 * @extends AbstractType<FlightComment>
 */
final class FlightCommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('body', TextareaType::class, [
                'label' => 'Commentaire',
                'attr' => ['rows' => 3, 'maxlength' => 2000],
            ])
            ->add('step', EnumType::class, [
                'class' => TrainingStep::class,
                'label' => 'Étape du parcours',
                'required' => false,
                'placeholder' => 'Aucune en particulier',
                'choice_label' => static fn (TrainingStep $step): string => $step->title(),
            ])
            ->add('atMoment', CheckboxType::class, [
                'label' => 'À cet instant du vol',
                'mapped' => false,
                'required' => false,
                'data' => true,
            ])
            ->add('at', HiddenType::class, [
                'attr' => ['data-flight-viewer-target' => 'commentAt'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FlightComment::class]);
    }
}
