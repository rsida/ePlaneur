<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\NetworkSession;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Network sessions: proposed by members on the site (`/sessions/nouvelle`, where their results are
 * added too) or imported from eplaneur.fr (`app:sessions:import`); corrected, given an organiser or
 * deleted here. Deleting a session keeps the tracks of its pilots.
 *
 * @extends AbstractCrudController<NetworkSession>
 */
#[IsGranted(Permission::SessionManage->value)]
final class NetworkSessionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return NetworkSession::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('session')
            ->setEntityLabelInPlural('Sessions en réseau')
            ->setDefaultSort(['date' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(['flightPlan.code', 'flightPlan.title', 'dedicatedTo', 'resultsFile']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $site = Action::new('site', 'Voir sur le site', 'external-link')
            ->linkToUrl(fn (NetworkSession $session): string => $this->urlGenerator->generate('app_session_show', ['id' => $session->getId()]));
        $propose = Action::new('propose', 'Proposer une session', 'plus')
            ->linkToUrl($this->urlGenerator->generate('app_session_new'))
            ->createAsGlobalAction();

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, $propose)
            ->add(Crud::PAGE_INDEX, $site)
            ->add(Crud::PAGE_EDIT, $site);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(DateTimeFilter::new('date', 'Date'))
            ->add(EntityFilter::new('flightPlan', 'Plan de vol'))
            ->add(NullFilter::new('dedicatedTo', 'Type')->setChoiceLabels('Ouverte', 'Dédiée'))
            ->add(NullFilter::new('raceId', 'Résultats')->setChoiceLabels('Sans résultats', 'Avec résultats'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield DateField::new('date', 'Date');
        yield TimeField::new('startTime', 'Briefing')->setFormat('HH:mm')->setHelp('Heure de métropole.');
        yield AssociationField::new('flightPlan', 'Plan de vol')->autocomplete();
        yield TextField::new('dedicatedTo', 'Session dédiée')->setHelp('Vide : session ouverte à tous.');
        yield AssociationField::new('organiser', 'Organisateur')->autocomplete();
        yield IntegerField::new('participantCount', 'Pilotes')->onlyOnIndex()->setSortable(false);
        yield UrlField::new('condorClubUrl', 'Circuit Condor Club')->hideOnIndex();
        yield TextareaField::new('notes', 'Informations pratiques')->hideOnIndex();
        yield TextField::new('resultsFile', 'Fichier de résultats')->hideOnForm()->hideOnIndex();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        foreach ($entityInstance->getFlights() as $flight) {
            $flight->setSession(null);
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }
}
