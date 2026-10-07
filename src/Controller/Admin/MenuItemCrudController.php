<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MenuItem;
use App\Navigation\MenuLocation;
use App\Security\Permission;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Navigation links of the header ("main", three levels) and footer (columns of links). A link
 * targets a page or an address; without either it is a heading grouping its children.
 *
 * @extends AbstractCrudController<MenuItem>
 */
#[IsGranted(Permission::MenuManage->value)]
final class MenuItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MenuItem::class;
    }

    public function createEntity(string $entityFqcn): MenuItem
    {
        return new MenuItem(MenuLocation::Main, '');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('lien de menu')
            ->setEntityLabelInPlural('Menus')
            ->setDefaultSort(['location' => 'DESC', 'parent' => 'ASC', 'position' => 'ASC'])
            ->setSearchFields(['label', 'url', 'description'])
            ->setPaginatorPageSize(100);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('location')->add('parent')->add('visibility');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Lien', 'link');
        yield ChoiceField::new('location', 'Menu');
        yield AssociationField::new('parent', 'Sous')
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb->orderBy('entity.location', 'DESC')->addOrderBy('entity.position', 'ASC'))
            ->setHelp('Vide pour un lien de premier niveau ; le parent doit appartenir au même menu.');
        yield TextField::new('label', 'Libellé')->setFormTypeOption('empty_data', '');
        yield TextField::new('description', 'Précision')
            ->setHelp('Note sous le lien (« Document PDF », « Lien externe ») ; pour un lien de premier niveau, le sur-titre du menu déroulant.')
            ->hideOnIndex();
        yield AssociationField::new('page', 'Page');
        yield TextField::new('url', 'Ou adresse')
            ->setHelp('Chemin du site (/actualites, /#vols), URL externe (https://…) ou mailto:.');
        yield IntegerField::new('position', 'Ordre');
        yield from VisibilityFields::create(announced: false);
    }
}
