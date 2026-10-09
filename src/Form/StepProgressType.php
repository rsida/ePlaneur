<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Flight;
use App\Entity\User;
use App\Training\ProgressStatus;
use App\Training\StepProgressData;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A step of the training path of a pilot, marked by a trainer: status, note, a flight of the
 * pilot that shows it, the day it was validated.
 *
 * @extends AbstractType<StepProgressData>
 */
final class StepProgressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $pilot = $options['pilot'];
        \assert($pilot instanceof User);
        $builder
            ->add('status', EnumType::class, [
                'class' => ProgressStatus::class,
                'label' => 'État',
                'required' => false,
                'placeholder' => 'Pas commencée',
                'choice_label' => static fn (ProgressStatus $status): string => $status->label(),
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Note du formateur',
                'required' => false,
                'help' => 'Ce qui est acquis, ce qui reste à travailler. Le pilote la lit dans son carnet.',
                'attr' => ['rows' => 3, 'maxlength' => 2000],
            ])
            ->add('flight', EntityType::class, [
                'class' => Flight::class,
                'label' => 'Vol de référence',
                'required' => false,
                'placeholder' => 'Aucun',
                'query_builder' => static fn (EntityRepository $flights): QueryBuilder => $flights->createQueryBuilder('f')
                    ->where('f.owner = :pilot')->setParameter('pilot', $pilot)->orderBy('f.uploadedAt', 'DESC'),
                'choice_label' => static fn (Flight $flight): string => $flight->getDate()->format('d/m/Y').' · '.($flight->getFlightPlan() ? explode('_', $flight->getFlightPlan())[0] : $flight->getOriginalName()),
            ])
            ->add('validatedOn', DateType::class, [
                'label' => 'Validée le',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Aujourd’hui si vide.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => StepProgressData::class]);
        $resolver->setRequired('pilot');
        $resolver->setAllowedTypes('pilot', User::class);
    }
}
