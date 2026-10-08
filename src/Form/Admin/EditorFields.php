<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Fields shared by the post and page editors (PostEditorType, PageEditorType): header texts,
 * address, catchphrase, publication date and block zones. Each form adds its own fields and the
 * access fields (AccessFields).
 */
final class EditorFields
{
    /**
     * Title and kicker (also edited in place in the canvas), slug generated from the title,
     * catchphrase on the right of the title.
     *
     * @param FormBuilderInterface<mixed> $builder
     */
    public static function addHeader(FormBuilderInterface $builder, string $slugHelp, string $highlightHelp): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'Titre', 'empty_data' => ''])
            ->add('slug', TextType::class, [
                'label' => 'Adresse',
                'empty_data' => '',
                'help' => $slugHelp,
                'attr' => ['data-ep-slug-source' => 'title'],
            ])
            ->add('kicker', TextType::class, ['label' => 'Surtitre', 'required' => false])
            ->add('highlight', TextareaType::class, ['label' => 'Phrase d’accroche', 'required' => false, 'help' => $highlightHelp])
            ->add('highlightNote', TextareaType::class, ['label' => 'Note sous l’accroche', 'required' => false]);
    }

    /**
     * Publication date, in the club's time (PHP's default timezone). Empty: draft.
     *
     * @param FormBuilderInterface<mixed> $builder
     */
    public static function addPublication(FormBuilderInterface $builder): void
    {
        $builder->add('publishedAt', DateTimeType::class, [
            'label' => 'Date de publication',
            'required' => false,
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'help' => 'Heure de France métropolitaine. Vide : brouillon.',
        ]);
    }

    /**
     * One hidden JSON field per block zone.
     *
     * @param FormBuilderInterface<mixed> $builder
     * @param array<string, string>       $zones   zone name => label in the editor
     */
    public static function addZones(FormBuilderInterface $builder, string $contentKind, array $zones): void
    {
        foreach (array_keys($zones) as $zone) {
            $builder->add($zone, BlockZoneType::class, ['content_kind' => $contentKind]);
        }
    }
}
