<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Back-office upload of one or more files into the media library, with their visibility.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class MediaUploadType extends AbstractType
{
    /** Files the site can show or offer for download */
    public const array MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml',
        'application/pdf', 'text/plain',
        'application/zip', 'application/x-zip-compressed',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('files', FileType::class, [
                'label' => 'Fichiers',
                'multiple' => true,
                'help' => 'Images (JPEG, PNG, WebP, GIF, SVG), PDF, texte ou ZIP ; 20 Mo maximum par fichier.',
                'constraints' => [
                    new Assert\Count(min: 1, minMessage: 'Choisissez au moins un fichier.'),
                    new Assert\All([new Assert\File(maxSize: '20M', mimeTypes: self::MIME_TYPES, mimeTypesMessage: 'Ce type de fichier n’est pas accepté.')]),
                ],
            ]);

        AccessFields::add($builder, mapped: false);
    }
}
