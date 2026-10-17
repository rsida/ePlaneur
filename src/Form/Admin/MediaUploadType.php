<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
        'video/mp4', 'video/webm',
    ];

    /** Formats read by the video player of the site (video block) */
    public const array VIDEO_MIME_TYPES = ['video/mp4', 'video/webm'];

    public const string HELP = 'Images (JPEG, PNG, WebP, GIF, SVG), PDF, texte, ZIP ou vidéo (MP4, WebM) ; 20 Mo maximum par fichier, 200 Mo pour une vidéo.';

    /**
     * Constraints of one uploaded file: a video may weigh up to 200 MB, any other file 20 MB.
     *
     * @return list<Assert\File|Assert\Callback>
     */
    public static function fileConstraints(): array
    {
        return [
            new Assert\File(maxSize: '200M', mimeTypes: self::MIME_TYPES, mimeTypesMessage: 'Ce type de fichier n’est pas accepté.', maxSizeMessage: 'Le fichier dépasse 200 Mo.'),
            new Assert\Callback(static function (mixed $file, ExecutionContextInterface $context): void {
                if ($file instanceof UploadedFile && $file->isValid() && !\in_array($file->getMimeType(), self::VIDEO_MIME_TYPES, true) && $file->getSize() > 20 * 1000 * 1000) {
                    $context->addViolation('Le fichier dépasse 20 Mo.');
                }
            }),
        ];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('files', FileType::class, [
                'label' => 'Fichiers',
                'multiple' => true,
                'help' => self::HELP,
                'constraints' => [
                    new Assert\Count(min: 1, minMessage: 'Choisissez au moins un fichier.'),
                    new Assert\All(self::fileConstraints()),
                ],
            ]);

        AccessFields::add($builder, mapped: false);
    }
}
