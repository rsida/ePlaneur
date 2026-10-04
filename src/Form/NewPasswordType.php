<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * New password typed twice, with the password policy (registration and password reset).
 *
 * @extends AbstractType<string>
 */
final class NewPasswordType extends AbstractType
{
    public const int MIN_LENGTH = 10;

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'type' => PasswordType::class,
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'options' => ['attr' => ['autocomplete' => 'new-password']],
            'first_options' => [
                'label' => 'Mot de passe',
                'help' => \sprintf('Au moins %d caractères. Une phrase de passe est idéale.', self::MIN_LENGTH),
            ],
            'second_options' => ['label' => 'Confirmez le mot de passe'],
            'constraints' => [
                new NotBlank(message: 'Choisissez un mot de passe.'),
                new Length(min: self::MIN_LENGTH, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                new PasswordStrength(message: 'Ce mot de passe est trop facile à deviner : allongez-le ou mélangez davantage de caractères.'),
            ],
        ]);
    }

    public function getParent(): string
    {
        return RepeatedType::class;
    }
}
