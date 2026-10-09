<?php

declare(strict_types=1);

namespace App\Form;

use App\Contact\ContactSubject;
use App\Entity\ContactRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Message to the club. The `website` field is a trap for robots: hidden to people, it must stay
 * empty (ContactController).
 *
 * @extends AbstractType<ContactRequest>
 */
final class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'attr' => ['autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'E-mail', 'attr' => ['autocomplete' => 'email']])
            ->add('subject', EnumType::class, [
                'class' => ContactSubject::class,
                'label' => 'Objet',
                'placeholder' => 'Choisissez l’objet',
                'choice_label' => static fn (ContactSubject $subject): string => $subject->label(),
            ])
            ->add('message', TextareaType::class, ['label' => 'Message', 'attr' => ['rows' => 7, 'maxlength' => 5000]])
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Ne remplissez pas ce champ',
                'row_attr' => ['class' => 'u-trap', 'aria-hidden' => 'true'],
                'attr' => ['tabindex' => '-1', 'autocomplete' => 'off'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactRequest::class]);
    }
}
