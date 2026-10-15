<?php

declare(strict_types=1);

namespace App\Form\Account;

use App\Account\NameShown;
use App\Form\NewPasswordType;
use App\Security\UserNotification;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The forms of the member space ("Mon profil", "Confidentialité et données"), one per panel, each
 * with its own name so that one page holds several. They work on arrays: the account changes only
 * once a form is valid.
 */
final readonly class ProfileForms
{
    public function __construct(
        private FormFactoryInterface $factory,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function displayName(array $data): FormInterface
    {
        return $this->factory->createNamedBuilder('display_name', FormType::class, $data)
            ->add('displayName', TextType::class, [
                'label' => 'Nom affiché',
                'help' => 'Utilisé dans vos échanges avec les membres et les formateurs.',
                'constraints' => [new Assert\NotBlank(message: 'Indiquez le nom à afficher.'), new Assert\Length(min: 2, max: 50)],
            ])
            ->add('nameShown', EnumType::class, [
                'class' => NameShown::class,
                'label' => 'Dans les classements, les records, les vols et les sessions, afficher',
                'choice_label' => static fn (NameShown $nameShown): string => $nameShown->label(),
                'expanded' => true,
                'help' => 'Le prénom est le premier mot de votre nom affiché. Conseillé aux pilotes mineurs.',
            ])
            ->add('pseudonym', TextType::class, [
                'label' => 'Pseudonyme',
                'required' => false,
                'help' => 'Choisissez aussi ce pseudonyme comme nom de pilote dans Condor : c’est le nom que le serveur écrit dans les résultats.',
                'constraints' => [new Assert\Length(min: 2, max: 30), new Assert\Regex(pattern: '/^[\p{L}\p{N}][\p{L}\p{N} ._-]*$/u', message: 'Lettres, chiffres, espaces, points, tirets et soulignés seulement.')],
            ])
            ->getForm();
    }

    /**
     * @return FormInterface<array<string, mixed>>
     */
    public function email(): FormInterface
    {
        return $this->factory->createNamedBuilder('email_change')
            ->add('email', EmailType::class, [
                'label' => 'Nouvelle adresse e-mail',
                'help' => 'Le changement prend effet après confirmation envoyée à la nouvelle adresse. Votre adresse actuelle reste utilisée tant que vous n’avez pas confirmé.',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [new Assert\NotBlank(message: 'Indiquez la nouvelle adresse.'), new Assert\Email(message: 'Saisissez une adresse complète, par exemple prenom.nom@example.fr.')],
            ])
            ->getForm();
    }

    /**
     * @return FormInterface<array<string, mixed>>
     */
    public function password(): FormInterface
    {
        return $this->factory->createNamedBuilder('password_change')
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new UserPassword(message: 'Ce n’est pas votre mot de passe actuel.')],
            ])
            ->add('plainPassword', NewPasswordType::class, [
                'first_options' => ['label' => 'Nouveau mot de passe', 'help' => \sprintf('Au moins %d caractères. Une phrase de passe est idéale.', NewPasswordType::MIN_LENGTH)],
                'second_options' => ['label' => 'Confirmez le nouveau mot de passe'],
            ])
            ->getForm();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function condor(array $data): FormInterface
    {
        return $this->factory->createNamedBuilder('condor', FormType::class, $data)
            ->add('condorCn', TextType::class, [
                'label' => 'CN Condor',
                'required' => false,
                'help' => 'Votre identifiant de compétition, identique à celui enregistré dans Condor.',
                'constraints' => [new Assert\Length(max: 10)],
            ])
            ->add('condorPilotName', TextType::class, [
                'label' => 'Nom pilote Condor',
                'required' => false,
                'help' => 'Copiez le nom utilisé dans le simulateur, accents compris.',
                'constraints' => [new Assert\Length(max: 100)],
            ])
            ->add('condorVersions', ChoiceType::class, [
                'label' => 'Versions de Condor',
                'choices' => ['Je vole sur Condor 2' => 2, 'Je vole sur Condor 3' => 3],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->getForm();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function notifications(array $data): FormInterface
    {
        return $this->factory->createNamedBuilder('notifications', FormType::class, $data)
            ->add('notifications', EnumType::class, [
                'class' => UserNotification::class,
                'label' => 'Je reçois un e-mail pour',
                'choice_label' => static fn (UserNotification $notification): string => $notification->label(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->getForm();
    }

    /**
     * What the devices of the account receive (panel "Notifications sur cet appareil").
     *
     * @param array<string, mixed> $data
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function push(array $data): FormInterface
    {
        return $this->factory->createNamedBuilder('push', FormType::class, $data)
            ->add('pushNotifications', EnumType::class, [
                'class' => UserNotification::class,
                'choices' => UserNotification::PUSH,
                'label' => 'Mes appareils reçoivent une notification pour',
                'choice_label' => static fn (UserNotification $notification): string => $notification->pushLabel(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->getForm();
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function privacy(array $data): FormInterface
    {
        return $this->factory->createNamedBuilder('privacy', FormType::class, $data)
            ->add('flightsPublicByDefault', CheckboxType::class, [
                'label' => 'Rendre mes nouveaux vols publics par défaut',
                'help' => 'Un vol public est visible des visiteurs du site, sans les commentaires. Cela ne change pas vos vols déjà déposés, que vous réglez dans « Mes vols ».',
                'required' => false,
            ])
            ->add('inRanking', CheckboxType::class, [
                'label' => 'Afficher mon nom dans le classement de saison',
                'help' => 'Vous pouvez retirer cette autorisation sans supprimer vos vols.',
                'required' => false,
            ])
            ->getForm();
    }

    /**
     * First step of the deletion: what becomes of the flights.
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function deletionChoice(int $flights): FormInterface
    {
        return $this->factory->createNamedBuilder('deletion', FormType::class, ['keepFlights' => '0'])
            ->add('keepFlights', ChoiceType::class, [
                'label' => \sprintf('Vos %d %s', $flights, $flights > 1 ? 'vols' : 'vol'),
                'choices' => [
                    'Supprimer aussi mes vols et mes traces' => '0',
                    'Garder mes vols de façon anonyme, sans nom ni CN, pour que les sessions et les records du club restent complets' => '1',
                ],
                'expanded' => true,
            ])
            ->getForm();
    }

    /**
     * Second step: the password and the acknowledgement.
     *
     * @return FormInterface<array<string, mixed>>
     */
    public function deletionConfirmation(string $keepFlights): FormInterface
    {
        return $this->factory->createNamedBuilder('deletion_confirmation', FormType::class, ['keepFlights' => $keepFlights])
            ->add('keepFlights', HiddenType::class)
            ->add('password', PasswordType::class, [
                'label' => 'Votre mot de passe actuel',
                'help' => 'Requis pour vérifier que vous êtes bien titulaire du compte.',
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [new Assert\NotBlank(message: 'Saisissez votre mot de passe.'), new UserPassword(message: 'Ce n’est pas votre mot de passe.')],
            ])
            ->add('understood', CheckboxType::class, [
                'label' => 'Je comprends que c’est définitif',
                'constraints' => [new Assert\IsTrue(message: 'Cochez la case pour confirmer.')],
            ])
            ->getForm();
    }
}
