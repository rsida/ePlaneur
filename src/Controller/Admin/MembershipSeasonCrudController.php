<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MembershipSeason;
use App\Form\Admin\MembershipTypeFormType;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Membership seasons: the year, whether requests are open, how to pay without online payment, and
 * the membership types with their prices.
 *
 * @extends AbstractCrudController<MembershipSeason>
 */
#[IsGranted(Permission::MembershipManage->value)]
final class MembershipSeasonCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MembershipSeason::class;
    }

    public function createEntity(string $entityFqcn): MembershipSeason
    {
        // From September, the campaign of the next season
        $now = $this->clock->now();

        return new MembershipSeason((int) $now->format('Y') + ((int) $now->format('n') >= 9 ? 1 : 0));
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('saison d’adhésion')
            ->setEntityLabelInPlural('Saisons d’adhésion')
            ->setDefaultSort(['year' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // A season with memberships is part of the books: it is closed, not deleted
        return $actions->disable(Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('year', 'Année')->setHelp('Les adhésions d’une saison se terminent le 31 décembre.');
        yield BooleanField::new('open', 'Demandes ouvertes')->renderAsSwitch()
            ->setHelp('Les membres peuvent envoyer leur demande pour cette saison depuis la page « Adhésion ». Ouvrir la saison suivante en fin d’année envoie les relances (commande app:membership:remind).');
        yield TextareaField::new('paymentInstructions', 'Instructions de paiement')->hideOnIndex()
            ->setHelp('Montrées après une demande quand le paiement en ligne n’est pas disponible : coordonnées bancaires pour un virement, contact du trésorier…');
        yield CollectionField::new('types', 'Types d’adhésion')
            ->setEntryType(MembershipTypeFormType::class)
            ->setFormTypeOption('by_reference', false)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex()
            ->setHelp('Un type déjà choisi par un membre ne peut pas être supprimé.');
    }
}
