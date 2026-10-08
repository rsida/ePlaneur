<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\MembershipType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A membership type inside the season form of the back-office: name, price, age limit, order.
 *
 * @extends AbstractType<MembershipType>
 */
final class MembershipTypeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'empty_data' => ''])
            ->add('price', MoneyType::class, ['label' => 'Prix', 'currency' => 'EUR', 'divisor' => 100, 'empty_data' => '0'])
            ->add('maxAge', IntegerType::class, ['label' => 'Âge maximum', 'required' => false, 'help' => 'Au 1er janvier de la saison, pour un tarif réduit (« Moins de 25 ans » : 24). Vide : sans limite.'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('position', IntegerType::class, ['label' => 'Ordre', 'empty_data' => '0']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MembershipType::class]);
    }
}
