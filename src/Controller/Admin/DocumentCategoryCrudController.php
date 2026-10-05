<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DocumentCategory;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Groups of official documents ("Textes fondateurs", "Décisions"...), in display order.
 *
 * @extends AbstractCrudController<DocumentCategory>
 */
#[IsGranted(Permission::DocumentManage->value)]
final class DocumentCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DocumentCategory::class;
    }

    public function createEntity(string $entityFqcn): DocumentCategory
    {
        return new DocumentCategory('', '');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('catégorie de documents')
            ->setEntityLabelInPlural('Catégories de documents')
            ->setDefaultSort(['position' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom')->setFormTypeOption('empty_data', '');
        yield SlugField::new('slug', 'Identifiant')->setTargetFieldName('name')->setFormTypeOption('empty_data', '')->hideOnIndex();
        yield IntegerField::new('position', 'Ordre')->setHelp('Les catégories s’affichent de la plus petite à la plus grande valeur.');
    }
}
