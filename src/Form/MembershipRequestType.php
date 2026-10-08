<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Membership;
use App\Entity\MembershipType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Membership request of a season: the membership type, the data of the membership form of the
 * internal rules, an optional support payment and the acceptance of the statutes and rules.
 *
 * @extends AbstractType<Membership>
 */
final class MembershipRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Membership $membership */
        $membership = $options['data'];
        $builder
            ->add('type', EntityType::class, [
                'class' => MembershipType::class,
                'choices' => $membership->getSeason()->getTypes(),
                'choice_label' => static fn (MembershipType $type): string => (string) $type,
                'expanded' => true,
                'label' => 'Type d’adhésion',
            ])
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'attr' => ['autocomplete' => 'given-name']])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'attr' => ['autocomplete' => 'family-name']])
            ->add('birthDate', DateType::class, ['label' => 'Date de naissance', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'attr' => ['autocomplete' => 'bday']])
            ->add('address', TextType::class, ['label' => 'Adresse', 'attr' => ['autocomplete' => 'street-address']])
            ->add('postalCode', TextType::class, ['label' => 'Code postal', 'attr' => ['autocomplete' => 'postal-code']])
            ->add('city', TextType::class, ['label' => 'Ville', 'attr' => ['autocomplete' => 'address-level2']])
            ->add('country', CountryType::class, ['label' => 'Pays', 'preferred_choices' => ['FR', 'BE', 'CH', 'CA']])
            ->add('phone', TelType::class, ['label' => 'Téléphone', 'help' => 'WhatsApp de préférence : c’est le principal canal du club.', 'attr' => ['autocomplete' => 'tel']])
            ->add('donation', MoneyType::class, [
                'label' => 'Soutien volontaire (facultatif)',
                'help' => 'Un montant libre en plus de la cotisation pour aider le club. Il ne donne pas de droit supplémentaire.',
                'currency' => 'EUR',
                'divisor' => 100,
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('rulesAccepted', CheckboxType::class, ['label' => 'J’ai lu les statuts et le règlement intérieur du club, et je les accepte.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Membership::class]);
    }
}
