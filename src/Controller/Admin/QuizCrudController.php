<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Quiz;
use App\Form\Admin\QuizQuestionFormType;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The quizzes of "Mon parcours": questions with their answers, the right one and its explanation,
 * and the pass mark. A quiz is placed in a step from the step form.
 *
 * @extends AbstractCrudController<Quiz>
 */
#[IsGranted(Permission::TrainingManage->value)]
final class QuizCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Quiz::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('quiz')
            ->setEntityLabelInPlural('Quiz')
            ->setDefaultSort(['title' => 'ASC'])
            ->setSearchFields(['title']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('intro', 'Introduction')->hideOnIndex();
        yield IntegerField::new('passMark', 'Réussi à (%)')
            ->setHelp('Part de bonnes réponses pour réussir le quiz.');
        yield IntegerField::new('questionCount', 'Questions')->onlyOnIndex()->setSortable(false);
        yield CollectionField::new('questions', 'Questions')->onlyOnForms()
            ->setEntryType(QuizQuestionFormType::class)
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex();
    }
}
