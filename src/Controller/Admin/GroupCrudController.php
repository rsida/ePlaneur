<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Group;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Groups and their permissions. The default groups (Membre, Comité, Administrateur) are "system"
 * groups: they can be renamed and get other permissions, but not be deleted, and no code changes.
 *
 * @extends AbstractCrudController<Group>
 */
#[IsGranted(Permission::GroupManage->value)]
final class GroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Group::class;
    }

    public function createEntity(string $entityFqcn): Group
    {
        return new Group('', '');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('groupe')
            ->setEntityLabelInPlural('Groupes et droits')
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action): Action => $action->displayIf(static fn (Group $group): bool => !$group->isSystem()));
    }

    public function configureFields(string $pageName): iterable
    {
        $permissions = [];
        foreach (Permission::cases() as $permission) {
            $permissions[$permission->label()] = $permission;
        }

        yield FormField::addFieldset('Informations du groupe');
        yield TextField::new('name', 'Nom')->setFormTypeOption('empty_data', '')->setColumns(6)
            ->setHelp('Nom affiché sur les comptes membres.');
        yield TextareaField::new('description', 'Description')->setColumns(6)->setNumOfRows(3)->hideOnIndex()
            ->setHelp('Description interne, non visible sur le site.');
        yield TextField::new('code', 'Code')->onlyWhenCreating()->setFormTypeOption('empty_data', '')->setColumns(6)
            ->setHelp('Identifiant technique définitif : minuscules, chiffres, - et _.');
        yield TextField::new('code', 'Code')->hideOnForm();
        yield AssociationField::new('users', 'Membres')->onlyOnIndex();

        yield FormField::addFieldset('Droits du groupe')->setHelp('Les droits des différents groupes d’un compte se cumulent.');
        yield BooleanField::new('allPermissions', 'Tous les droits')
            ->setHelp('Réservé aux administrateurs du site : donne tous les droits, y compris les futurs.')
            ->renderAsSwitch(false) // no one-click toggle on the list
            ->setFormTypeOption('label_attr', ['class' => 'checkbox-switch']);
        yield ChoiceField::new('permissions', 'Droits')
            ->setChoices($permissions)
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setRequired(false)
            ->addCssClass('ep-choices-columns')
            ->setColumns(12)
            ->hideOnIndex();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance->isSystem()) {
            $this->addFlash('danger', 'Les groupes par défaut ne peuvent pas être supprimés.');

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
