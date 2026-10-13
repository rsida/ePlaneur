<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\PathItem;
use App\Entity\Quiz;
use App\Training\PathItemType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * An item of a step of "Mon parcours" inside the step form of the back-office.
 *
 * @extends AbstractType<PathItem>
 */
final class PathItemFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('position', IntegerType::class, ['label' => 'Ordre', 'empty_data' => '0'])
            ->add('type', EnumType::class, [
                'label' => 'Type',
                'class' => PathItemType::class,
                'choice_label' => static fn (PathItemType $type): string => $type->label(),
                'placeholder' => 'Choisir…',
            ])
            ->add('title', TextType::class, ['label' => 'Titre', 'empty_data' => ''])
            ->add('text', TextareaType::class, ['label' => 'Consigne', 'required' => false, 'help' => 'Ce qu’il faut faire et pourquoi.'])
            ->add('url', TextType::class, ['label' => 'Adresse de la ressource', 'required' => false, 'help' => 'Page du site (/formation/…), document, vidéo YouTube… Vide : aucune.'])
            ->add('flightPlan', TextType::class, ['label' => 'Plan de vol de l’exercice', 'required' => false, 'help' => 'Exercice en vol : code du plan de vol (C2-d015). Un circuit terminé dessus valide l’exercice. Vide : le pilote dit lui-même quand c’est fait.'])
            ->add('quiz', EntityType::class, ['label' => 'Quiz', 'class' => Quiz::class, 'required' => false, 'placeholder' => 'Aucun', 'help' => 'Pour un élément de type Quiz (menu « Quiz » pour les écrire).'])
            ->add('rankingFrom', IntegerType::class, ['label' => 'Classement à partir de', 'empty_data' => (string) PathItem::RANKING_FROM, 'help' => 'Exercice en vol : nombre de pilotes qui l’ont terminé avant d’afficher le classement.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PathItem::class]);
    }
}
