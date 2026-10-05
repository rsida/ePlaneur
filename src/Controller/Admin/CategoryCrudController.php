<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @extends AbstractCrudController<Category>
 */
#[IsGranted(Permission::CategoryManage->value)]
final class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function createEntity(string $entityFqcn): Category
    {
        return new Category('', '');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('catégorie')
            ->setEntityLabelInPlural('Catégories d’articles')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'slug']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom')->setFormTypeOption('empty_data', '');
        yield SlugField::new('slug', 'Adresse')->setTargetFieldName('name')->setFormTypeOption('empty_data', '')
            ->setHelp('Partie de l’URL, en minuscules et tirets.');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
    }
}
