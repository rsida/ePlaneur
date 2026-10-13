<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Category;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Settings of a post in the block editor ("Article" tab) and its three block zones. Title, kicker
 * and lead are also edited in place in the canvas. The publication date is only part of the form
 * for users allowed to publish (`can_publish`), the author for those who edit every post
 * (`can_choose_author`).
 *
 * @extends AbstractType<Post>
 */
final class PostEditorType extends AbstractType
{
    /** Block zones of a post, in reading order, with their label in the editor. */
    public const array ZONES = ['body' => 'Corps (lecture)', 'aside' => 'Barre latérale', 'outro' => 'Bande finale · Pleine largeur'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        EditorFields::addHeader($builder, 'Lettres minuscules, chiffres et tirets.', 'À droite du titre ; une ligne par phrase.');
        EditorFields::addZones($builder, 'post', self::ZONES);
        $builder
            ->add('excerpt', TextareaType::class, ['label' => 'Chapô', 'empty_data' => '', 'help' => 'Aussi utilisé dans les cartes et par les moteurs de recherche.'])
            ->add('badge', TextType::class, ['label' => 'Badge de niveau', 'required' => false, 'help' => 'Par exemple « Débutant ».'])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Category::class,
                'required' => false,
                'placeholder' => 'Aucune',
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('category')->orderBy('category.name', 'ASC'),
            ])
            ->add('keywords', TextType::class, ['label' => 'Mots-clés', 'required' => false, 'help' => 'Séparés par des virgules.'])
            ->add('cover', MediaPickerType::class, ['label' => 'Couverture'])
            ->add('coverCaption', TextType::class, ['label' => 'Phrase sur la couverture', 'required' => false])
            ->add('featured', CheckboxType::class, ['label' => 'À la une', 'required' => false, 'label_attr' => ['class' => 'checkbox-switch']]);

        $builder->get('keywords')->addModelTransformer(new CallbackTransformer(
            static fn (?array $keywords): string => implode(', ', $keywords ?? []),
            static fn (?string $keywords): array => array_values(array_filter(array_map(trim(...), explode(',', $keywords ?? '')), static fn (string $keyword): bool => '' !== $keyword)),
        ));

        AccessFields::add($builder);
        SearchFields::add($builder, 'la couverture, sinon l’image du site');

        // Other writers' posts are only reassigned by those who edit every post
        if ($options['can_choose_author']) {
            $builder->add('author', EntityType::class, [
                'label' => 'Auteur',
                'class' => User::class,
                'required' => false,
                'placeholder' => 'Aucun',
                'choice_label' => 'displayName',
                'query_builder' => static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('user')->orderBy('user.displayName', 'ASC'),
            ]);
        }
        if ($options['can_publish']) {
            EditorFields::addPublication($builder);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'can_publish' => false,
            'can_choose_author' => false,
            'empty_data' => static fn (): Post => new Post('', ''),
        ]);
        $resolver->setAllowedTypes('can_publish', 'bool');
        $resolver->setAllowedTypes('can_choose_author', 'bool');
    }
}
