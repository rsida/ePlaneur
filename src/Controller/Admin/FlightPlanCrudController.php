<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\FlightPlan;
use App\Repository\NetworkSessionRepository;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Flight plans of the sessions: recorded when a session or a track uses a new code, corrected here
 * (route, address of the sheet on eplaneur.fr). A flight plan used by a session cannot be deleted.
 *
 * @extends AbstractCrudController<FlightPlan>
 */
#[IsGranted(Permission::SessionManage->value)]
final class FlightPlanCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly NetworkSessionRepository $sessions,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return FlightPlan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('plan de vol')
            ->setEntityLabelInPlural('Plans de vol')
            ->setDefaultSort(['code' => 'ASC'])
            ->setSearchFields(['code', 'title', 'landscape']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $site = Action::new('site', 'Voir sur le site', 'external-link')
            ->linkToUrl(fn (FlightPlan $plan): string => $this->urlGenerator->generate('app_flight_plan_show', ['code' => $plan->getCode()]));

        return $actions
            ->add(Crud::PAGE_INDEX, $site)
            ->add(Crud::PAGE_EDIT, $site)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action): Action => $action
                ->displayIf(fn (FlightPlan $plan): bool => [] === $this->sessions->findByFlightPlan($plan)));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'Code')->setHelp('Le code de la bibliothèque eplaneur.fr : version de Condor, catégorie (d initiation, s standard, c compétition), numéro, variante. Exemple : C3-c046a.');
        yield TextField::new('title', 'Parcours');
        yield TextField::new('gliders', 'Planeurs');
        yield TextField::new('landscape', 'Scène');
        yield UrlField::new('sheetUrl', 'Fiche sur eplaneur.fr')->hideOnIndex();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ([] !== $this->sessions->findByFlightPlan($entityInstance)) {
            $this->addFlash('danger', 'Ce plan de vol est celui de sessions : il ne peut pas être supprimé.');

            return;
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }
}
