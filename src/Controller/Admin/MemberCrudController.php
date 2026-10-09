<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Admin\Filter\MembershipStateFilter;
use App\Entity\User;
use App\Flight\Logbook;
use App\Repository\MembershipRepository;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Members, read only, for the committee: search by name, e-mail or Condor CN, filter by group and by
 * membership of the year; the record of a member gathers their memberships, their flights and the
 * figures of their logbook. Accounts are edited in "Comptes" (USER_MANAGE).
 *
 * @extends AbstractCrudController<User>
 */
#[IsGranted(Permission::MembershipManage->value)]
final class MemberCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MembershipRepository $memberships,
        private readonly Logbook $logbook,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('membre')
            ->setEntityLabelInPlural('Membres')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (User $user): string => $user->getDisplayName())
            ->setDefaultSort(['displayName' => 'ASC'])
            ->setSearchFields(['displayName', 'email', 'condorCn', 'condorPilotName'])
            ->overrideTemplate('crud/detail', 'admin/member/detail.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $account = Action::new('account', 'Modifier le compte', 'pencil')
            ->linkToUrl(fn (User $user): string => $this->container->get(AdminUrlGeneratorInterface::class)->setController(UserCrudController::class)->setAction(Action::EDIT)->setEntityId($user->getId())->generateUrl())
            ->displayIf(fn (): bool => $this->isGranted(Permission::UserManage->value));

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_DETAIL, $account);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('groups')->add(MembershipStateFilter::new());
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('displayName', 'Nom');
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('condorCn', 'CN');
        // Shown from the account itself: names of its groups, and its membership of the year
        yield Field::new('groups', 'Groupes')
            ->setTemplatePath('admin/field/group_names.html.twig')
            ->setSortable(false);
        yield Field::new('id', 'Adhésion '.$this->clock->now()->format('Y'))
            ->setTemplatePath('admin/field/membership_of_year.html.twig')
            ->setSortable(false);
        yield DateTimeField::new('createdAt', 'Inscription');
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_DETAIL === $responseParameters->get('pageName')) {
            $user = $responseParameters->get('entity')->getInstance();
            \assert($user instanceof User);
            $responseParameters->set('memberships', $this->memberships->findByUser($user));
            $responseParameters->set('logbook', $this->logbook->of($user));
        }

        return $responseParameters;
    }
}
