<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Accounts. They are created by registration (free account), so there is no "new" page; validating
 * a membership means adding the "Membre" group. Passwords are never shown nor set here: members use
 * "Mot de passe oublié".
 *
 * @extends AbstractCrudController<User>
 */
#[IsGranted(Permission::UserManage->value)]
final class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('compte')
            ->setEntityLabelInPlural('Comptes')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['displayName', 'email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action): Action => $action->displayIf(fn (User $user): bool => $user !== $this->getUser()));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('groups')->add('verified');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Compte', 'fa fa-user');
        yield TextField::new('displayName', 'Nom affiché')->setFormTypeOption('empty_data', '');
        yield EmailField::new('email', 'E-mail')->setFormTypeOption('empty_data', '');
        yield BooleanField::new('verified', 'E-mail confirmé')->renderAsSwitch(false);
        yield AssociationField::new('groups', 'Groupes')
            ->setFormTypeOptions(['by_reference' => false, 'expanded' => true])
            ->setHelp('Ajoutez « Membre » une fois l’adhésion payée sur Yapla et validée.');
        yield DateTimeField::new('createdAt', 'Inscription')->hideOnForm();

        yield FormField::addFieldset('Profil d’auteur', 'fa fa-pen');
        yield TextField::new('jobTitle', 'Fonction')->hideOnIndex();
        yield TextareaField::new('bio', 'Présentation')->hideOnIndex();
        yield AssociationField::new('avatar', 'Photo')
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb->andWhere('entity.mimeType LIKE :image')->setParameter('image', 'image/%'))
            ->hideOnIndex();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte ici.');

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
