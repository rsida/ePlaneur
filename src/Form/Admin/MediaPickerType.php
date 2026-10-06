<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Media;
use App\Repository\MediaRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A media of the library chosen in the editor's media window: a hidden id with a preview and
 * "Choisir" / "Retirer" buttons (templates/admin/editor/_form_theme.html.twig).
 *
 * @extends AbstractType<Media|null>
 */
final class MediaPickerType extends AbstractType
{
    public function __construct(
        private readonly MediaRepository $media,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?Media $media): string => (string) ($media?->getId() ?? ''),
            function (?string $id): ?Media {
                if (null === $id || '' === $id) {
                    return null;
                }

                return $this->media->find((int) $id) ?? throw new TransformationFailedException('Unknown media.', 0, null, 'Ce média n’existe plus : choisissez-en un autre.');
            },
        ));
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['media'] = $form->getData();
        $view->vars['accept'] = $options['accept'];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['accept' => 'image', 'required' => false]);
        $resolver->setAllowedValues('accept', ['image', 'pdf', 'file']);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'media_picker';
    }
}
