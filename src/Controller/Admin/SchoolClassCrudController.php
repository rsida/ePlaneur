<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SchoolClass;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Classes of the teachers (roadmap 11f, doc/teacher-space.md): created by the teachers in "Mes
 * classes"; here an administrator sees them all, gives a class to another teacher, removes a pupil
 * or deletes a class.
 *
 * @extends AbstractCrudController<SchoolClass>
 */
#[IsGranted(Permission::UserManage->value)]
final class SchoolClassCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SchoolClass::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('classe')
            ->setEntityLabelInPlural('Classes')
            ->setDefaultSort(['createdAt' => 'DESC', 'id' => 'DESC'])
            ->setSearchFields(['name', 'school', 'teacher.displayName', 'teacher.email']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield TextField::new('school', 'Établissement');
        yield AssociationField::new('teacher', 'Enseignant')->autocomplete()
            ->setHelp('Il lui faut le droit « Créer des classes et suivre la progression de ses élèves » pour suivre la classe.');
        yield AssociationField::new('pupils', 'Élèves')->autocomplete()->setFormTypeOption('by_reference', false);
        yield BooleanField::new('open', 'Lien ouvert')->renderAsSwitch(false)
            ->setHelp('Le lien permet aux élèves de rejoindre la classe.');
        yield DateTimeField::new('createdAt', 'Créée le')->hideOnForm();
    }
}
