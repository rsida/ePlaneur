<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Page;
use App\Repository\PageRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Settings of a page in the block editor ("Page" tab) and its two block zones. Title, kicker and
 * lead are also edited in place in the canvas.
 *
 * @extends AbstractType<Page>
 */
final class PageEditorType extends AbstractType
{
    public function __construct(
        private readonly PageRepository $pages,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Page|null $edited */
        $edited = $builder->getData();
        $tree = array_filter($this->pages->findTree(), static fn (array $node): bool => null === $edited?->getId() || !\in_array($edited, $node['page']->getLineage(), true));
        $depths = [];
        foreach ($tree as $node) {
            $depths[spl_object_id($node['page'])] = $node['depth'];
        }

        $builder
            ->add('title', TextType::class, ['label' => 'Titre', 'empty_data' => ''])
            ->add('parent', EntityType::class, [
                'label' => 'Page parente',
                'class' => Page::class,
                'required' => false,
                'placeholder' => 'Aucune (rubrique du site)',
                // Tree order; a page cannot be moved under itself or one of its sub-pages
                'choices' => array_column($tree, 'page'),
                'choice_label' => static fn (Page $page): string => str_repeat('— ', $depths[spl_object_id($page)] ?? 0).$page->getTitle(),
                'attr' => ['data-ep-reload' => 'true'],
            ])
            ->add('slug', TextType::class, [
                'label' => 'Adresse',
                'empty_data' => '',
                'help' => 'Dernière partie du chemin : lettres minuscules, chiffres et tirets.',
                'attr' => ['data-ep-slug-source' => 'title'],
            ])
            ->add('position', IntegerType::class, ['label' => 'Ordre parmi les pages sœurs', 'empty_data' => '0'])
            ->add('kicker', TextType::class, ['label' => 'Surtitre', 'required' => false])
            ->add('excerpt', TextareaType::class, ['label' => 'Chapô', 'required' => false, 'help' => 'Aussi utilisé dans les cartes des sous-pages.'])
            ->add('linkLabel', TextType::class, ['label' => 'Libellé du lien', 'required' => false, 'help' => 'Texte des liens vers cette page dans les cartes.'])
            ->add('highlight', TextareaType::class, ['label' => 'Phrase d’accroche', 'required' => false, 'help' => 'Vide : celle de la page parente.'])
            ->add('highlightNote', TextareaType::class, ['label' => 'Note sous l’accroche', 'required' => false])
            ->add('updatedAt', DateType::class, [
                'label' => 'Date de mise à jour',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Affichée sous le titre des sous-pages. Elle passe à aujourd’hui à chaque enregistrement, sauf si vous choisissez une autre date.',
            ])
            ->add('publishedAt', DateTimeType::class, [
                'label' => 'Date de publication',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'view_timezone' => 'Europe/Paris',
                'help' => 'Heure de France métropolitaine. Vide : brouillon.',
            ])
            ->add('body', BlockZoneType::class, ['content_kind' => 'page'])
            ->add('aside', BlockZoneType::class, ['content_kind' => 'page']);

        AccessFields::add($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Page::class,
            'empty_data' => static fn (): Page => new Page('', ''),
        ]);
    }
}
