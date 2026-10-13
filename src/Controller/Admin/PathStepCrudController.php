<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PathStep;
use App\Form\Admin\PathItemFormType;
use App\Repository\PathStepRepository;
use App\Security\Permission;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The eight steps of "Mon parcours": open or not (the path stops at the first step not open), the
 * introduction, and the items that complete each step. Steps are neither created nor deleted.
 *
 * @extends AbstractCrudController<PathStep>
 */
#[IsGranted(Permission::TrainingManage->value)]
final class PathStepCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly PathStepRepository $steps,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return PathStep::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('étape du parcours')
            ->setEntityLabelInPlural('Mon parcours : étapes')
            ->setPageTitle(Crud::PAGE_EDIT, static fn (PathStep $step): string => $step->getStep()->title())
            ->setDefaultSort(['step' => 'ASC'])
            ->setPaginatorPageSize(8)
            ->setHelp(Crud::PAGE_INDEX, 'Le parcours s’arrête à la première étape non ouverte : les suivantes sont annoncées « Bientôt ». Une étape est terminée quand tous ses éléments sont faits, ou quand un formateur la valide dans le carnet du pilote.');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, Action::new('see', 'Voir le parcours', 'external-link')->linkToRoute('app_path')->createAsGlobalAction());
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        // The eight steps exist before they are listed
        $this->steps->findPath();

        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('number', 'Étape')->onlyOnIndex();
        yield TextField::new('title', 'Titre')->onlyOnIndex();
        yield BooleanField::new('open', 'Ouverte')->renderAsSwitch()
            ->setHelp('Les pilotes peuvent faire cette étape. Ouvrez-la quand ses éléments sont prêts.');
        yield IntegerField::new('itemCount', 'Éléments')->onlyOnIndex()->setSortable(false);
        yield TextareaField::new('intro', 'Introduction')->hideOnIndex()
            ->setHelp('Objectifs de l’étape et ce que le pilote saura faire à la fin.');
        yield CollectionField::new('items', 'Éléments de l’étape')->onlyOnForms()
            ->setEntryType(PathItemFormType::class)
            ->setFormTypeOption('by_reference', false)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex()
            ->setHelp('Lectures, exercices en vol, quiz et validation par un formateur, dans l’ordre. Supprimer un élément efface ce que les pilotes y ont fait.');
    }
}
