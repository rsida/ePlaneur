<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Content\Editor\InvalidZoneException;
use App\Content\Editor\ZoneNormalizer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A block zone (body, aside, outro) edited by the block editor: a hidden field holding the blocks
 * as JSON. Submitted blocks are read through their classes; an invalid one makes the field invalid.
 *
 * @extends AbstractType<list<array{type: string, data: array<string, mixed>}>>
 */
final class BlockZoneType extends AbstractType
{
    public function __construct(
        private readonly ZoneNormalizer $normalizer,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            static fn (?array $blocks): string => json_encode($blocks ?? [], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE),
            function (?string $json) use ($options): array {
                try {
                    return $this->normalizer->normalize(json_decode($json ?? '[]', true, 64, \JSON_THROW_ON_ERROR), $options['content_kind']);
                } catch (\JsonException) {
                    throw new TransformationFailedException('Invalid JSON.', 0, null, 'La liste des blocs est illisible.');
                } catch (InvalidZoneException $exception) {
                    throw new TransformationFailedException($exception->getMessage(), 0, $exception, implode(' ', $exception->errors));
                }
            },
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('content_kind')->setAllowedValues('content_kind', ['post', 'page']);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
