<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Category;
use App\Entity\Group;
use App\Entity\Post;
use App\Entity\User;
use App\Security\Visibility;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Settings of a post in the block editor ("Article" tab) and its three block zones. Title, kicker
 * and lead are also edited in place in the canvas. The publication date is only part of the form
 * for users allowed to publish.
 *
 * @extends AbstractType<Post>
 */
final class PostEditorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre', 'empty_data' => ''])
            ->add('slug', TextType::class, [
                'label' => 'Adresse',
                'empty_data' => '',
                'help' => 'Lettres minuscules, chiffres et tirets.',
                'attr' => ['data-ep-slug-source' => 'title'],
            ])
            ->add('excerpt', TextareaType::class, ['label' => 'Chapô', 'empty_data' => '', 'help' => 'Aussi utilisé dans les cartes et par les moteurs de recherche.'])
            ->add('kicker', TextType::class, ['label' => 'Surtitre', 'required' => false])
            ->add('badge', TextType::class, ['label' => 'Badge de niveau', 'required' => false, 'help' => 'Par exemple « Débutant ».'])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Category::class,
                'required' => false,
                'placeholder' => 'Aucune',
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('category')->orderBy('category.name', 'ASC'),
            ])
            ->add('keywords', TextType::class, ['label' => 'Mots-clés', 'required' => false, 'help' => 'Séparés par des virgules.'])
            ->add('author', EntityType::class, [
                'label' => 'Auteur',
                'class' => User::class,
                'required' => false,
                'placeholder' => 'Aucun',
                'choice_label' => 'displayName',
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('user')->orderBy('user.displayName', 'ASC'),
            ])
            ->add('cover', MediaPickerType::class, ['label' => 'Couverture'])
            ->add('coverCaption', TextType::class, ['label' => 'Phrase sur la couverture', 'required' => false])
            ->add('highlight', TextareaType::class, ['label' => 'Phrase d’accroche', 'required' => false, 'help' => 'À droite du titre ; une ligne par phrase.'])
            ->add('highlightNote', TextareaType::class, ['label' => 'Note sous l’accroche', 'required' => false])
            ->add('featured', CheckboxType::class, ['label' => 'À la une', 'required' => false, 'label_attr' => ['class' => 'checkbox-switch']])
            ->add('visibility', EnumType::class, [
                'label' => 'Visible par',
                'class' => Visibility::class,
                'choice_label' => static fn (Visibility $visibility): string => $visibility->label(),
            ])
            ->add('allowedGroups', EntityType::class, [
                'label' => 'Groupes autorisés',
                'class' => Group::class,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'help' => 'Utilisé seulement avec « Groupes choisis ».',
            ])
            ->add('body', BlockZoneType::class, ['content_kind' => 'post'])
            ->add('aside', BlockZoneType::class, ['content_kind' => 'post'])
            ->add('outro', BlockZoneType::class, ['content_kind' => 'post']);

        $builder->get('keywords')->addModelTransformer(new CallbackTransformer(
            static fn (?array $keywords): string => implode(', ', $keywords ?? []),
            static fn (?string $keywords): array => array_values(array_filter(array_map(trim(...), explode(',', $keywords ?? '')), static fn (string $keyword): bool => '' !== $keyword)),
        ));

        if ($options['can_publish']) {
            $builder->add('publishedAt', DateTimeType::class, [
                'label' => 'Date de publication',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'view_timezone' => 'Europe/Paris',
                'help' => 'Heure de France métropolitaine. Vide : brouillon.',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'can_publish' => false,
            'empty_data' => static fn (): Post => new Post('', ''),
        ]);
        $resolver->setAllowedTypes('can_publish', 'bool');
    }
}
