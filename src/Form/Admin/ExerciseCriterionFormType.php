<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Training\CriterionRow;
use App\Training\ExerciseCriterion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A criterion of an exercise in the back-office: what the analysis checks and its value.
 *
 * @extends AbstractType<CriterionRow>
 */
final class ExerciseCriterionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('criterion', EnumType::class, [
                'label' => 'Critère',
                'class' => ExerciseCriterion::class,
                'choice_label' => static fn (ExerciseCriterion $criterion): string => match ($criterion->kind()) {
                    ExerciseCriterion::FACT => $criterion->label(),
                    ExerciseCriterion::AT_MOST => $criterion->label().' (au plus'.('' !== $criterion->unit() ? ', '.$criterion->unit() : '').')',
                    default => $criterion->label().' (au moins'.('' !== $criterion->unit() ? ', '.$criterion->unit() : '').')',
                },
            ])
            ->add('value', NumberType::class, ['label' => 'Valeur', 'required' => false, 'scale' => 2, 'help' => 'Vide pour « Circuit terminé » et « Posé sur un terrain ».']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CriterionRow::class, 'empty_data' => static fn (): CriterionRow => new CriterionRow()]);
    }
}
