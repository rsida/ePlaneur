<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Official documents. The file comes from the media library (PDF); its visibility follows the
 * document's (DocumentAccessListener).
 *
 * @extends AbstractCrudController<Document>
 */
#[IsGranted(Permission::DocumentManage->value)]
final class DocumentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Document::class;
    }

    public function createEntity(string $entityFqcn): Document
    {
        return new Document('');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('document')
            ->setEntityLabelInPlural('Documents officiels')
            ->setDefaultSort(['category' => 'ASC', 'position' => 'ASC'])
            ->setSearchFields(['title', 'description', 'version']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('category')->add('visibility');
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Document', 'files');
        yield TextField::new('title', 'Titre')->setFormTypeOption('empty_data', '');
        yield AssociationField::new('category', 'Catégorie');
        yield AssociationField::new('file', 'Fichier')
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb->orderBy('entity.uploadedAt', 'DESC'))
            ->setHelp('Téléversez d’abord le fichier dans la médiathèque.');
        yield TextareaField::new('description', 'Description')->setNumOfRows(2)->hideOnIndex();
        yield TextField::new('version', 'Version')->setHelp('Par exemple « V13 ».');
        yield ArrayField::new('details', 'Mentions datées')
            ->setHelp('Une ligne par mention : « Adoptés le 15/12/2025 », « Publication : 03/02/2026 ».')
            ->hideOnIndex();
        yield IntegerField::new('position', 'Ordre')->hideOnIndex();
        yield DateTimeField::new('updatedAt', 'Mis à jour')->hideOnForm();
        yield from VisibilityFields::create();
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $entityInstance->setUpdatedAt(new \DateTimeImmutable());
        parent::updateEntity($entityManager, $entityInstance);
    }
}
