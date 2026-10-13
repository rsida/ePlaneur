<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Page;
use App\Repository\PageRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
    /** Block zones of a page with their label in the editor. */
    public const array ZONES = ['body' => 'Corps', 'aside' => 'Colonne latérale'];

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

        EditorFields::addHeader($builder, 'Dernière partie du chemin : lettres minuscules, chiffres et tirets.', 'Vide : celle de la page parente.');
        EditorFields::addPublication($builder);
        EditorFields::addZones($builder, 'page', self::ZONES);
        $builder
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
            ->add('position', IntegerType::class, ['label' => 'Ordre parmi les pages sœurs', 'empty_data' => '0'])
            ->add('excerpt', TextareaType::class, ['label' => 'Chapô', 'required' => false, 'help' => 'Aussi utilisé dans les cartes des sous-pages.'])
            ->add('linkLabel', TextType::class, ['label' => 'Libellé du lien', 'required' => false, 'help' => 'Texte des liens vers cette page dans les cartes.'])
            ->add('updatedAt', DateType::class, [
                'label' => 'Date de mise à jour',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'help' => 'Affichée sous le titre des sous-pages. Elle passe à aujourd’hui à chaque enregistrement, sauf si vous choisissez une autre date.',
            ]);

        AccessFields::add($builder);
        SearchFields::add($builder, 'l’image du site');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Page::class,
            'empty_data' => static fn (): Page => new Page('', ''),
        ]);
    }
}
