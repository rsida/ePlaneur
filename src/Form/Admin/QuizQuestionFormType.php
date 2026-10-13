<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * A question of a quiz in the back-office: the answers one per line, the number of the right one
 * (from 1); stored as App\Entity\Quiz questions (answers as a list, right answer from 0).
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class QuizQuestionFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('question', TextType::class, ['label' => 'Question'])
            ->add('choices', TextareaType::class, ['label' => 'Réponses proposées', 'help' => 'Une réponse par ligne.', 'attr' => ['rows' => 4]])
            ->add('answer', IntegerType::class, ['label' => 'Numéro de la bonne réponse', 'help' => '1 pour la première ligne, 2 pour la deuxième…', 'attr' => ['min' => 1]])
            ->add('explanation', TextareaType::class, ['label' => 'Explication', 'required' => false, 'help' => 'Montrée après la réponse : pourquoi c’est la bonne.']);

        $builder->get('choices')->addModelTransformer(new CallbackTransformer(
            static fn (?array $choices): string => implode("\n", $choices ?? []),
            static fn (?string $text): array => array_values(array_filter(array_map(trim(...), preg_split('/\R/', (string) $text) ?: []), static fn (string $line): bool => '' !== $line)),
        ));
        $builder->get('answer')->addModelTransformer(new CallbackTransformer(
            static fn (?int $index): ?int => null !== $index ? $index + 1 : null,
            static fn (?int $number): int => (int) $number - 1,
        ));
    }
}
