<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Asks for the e-mail address of an account (password reset, new confirmation link).
 *
 * @extends AbstractType<array{email: string}>
 */
final class AccountEmailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Adresse e-mail du compte',
            'attr' => ['autocomplete' => 'email'],
            'constraints' => [
                new NotBlank(message: 'Indiquez votre adresse e-mail.'),
                new Email(),
            ],
        ]);
    }
}
