<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A new conversation (MessageController::new()): who to write to, among the `recipients` option
 * (["Le club" => [name => "group:<code>"], "Membres" => [name => "user:<id>"]]), the subject and
 * the first message.
 *
 * @extends AbstractType<array{recipient: ?string, subject: ?string, body: ?string}>
 */
final class ConversationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipient', ChoiceType::class, [
                'label' => 'Destinataire',
                'choices' => $options['recipients'],
                'placeholder' => 'Choisissez un membre, le comité ou les administrateurs',
                'constraints' => [new Assert\NotBlank(message: 'Choisissez à qui écrire.')],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Objet',
                'constraints' => [new Assert\NotBlank(message: 'Donnez un objet à votre message.'), new Assert\Length(max: 160)],
                'attr' => ['maxlength' => 160],
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Message',
                'constraints' => [new Assert\NotBlank(message: 'Écrivez votre message.'), new Assert\Length(max: 5000)],
                'attr' => ['rows' => 7, 'maxlength' => 5000],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('recipients');
        $resolver->setAllowedTypes('recipients', 'array');
    }
}
