<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Contact\ContactSubject;
use App\Entity\ContactRequest;
use App\Entity\User;
use App\Security\Permission;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Messages of the contact form: read them, answer by e-mail (the address is a mailto link), note
 * what was done and mark them processed. They are not created here; deleting one is possible.
 *
 * @extends AbstractCrudController<ContactRequest>
 */
#[IsGranted(Permission::ContactManage->value)]
final class ContactRequestCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContactRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('message de contact')
            ->setEntityLabelInPlural('Messages de contact')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'email', 'message']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $step = fn (string $name, string $label, string $icon): Action => Action::new($name, $label, $icon)
            ->linkToRoute('admin_contact_request_'.$name, fn (ContactRequest $request): array => [
                'id' => $request->getId(),
                'token' => $this->csrfTokenManager->getToken($name.$request->getId())->getValue(),
            ])
            ->renderAsForm();
        $processed = $step('processed', 'Marquer comme traité', 'circle-check')->asSuccessAction()
            ->displayIf(static fn (ContactRequest $request): bool => !$request->isProcessed());
        $reopen = $step('reopen', 'Rouvrir', 'undo-2')
            ->displayIf(static fn (ContactRequest $request): bool => $request->isProcessed());

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $processed)->add(Crud::PAGE_DETAIL, $processed)
            ->add(Crud::PAGE_DETAIL, $reopen);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $subjects = [];
        foreach (ContactSubject::cases() as $subject) {
            $subjects[$subject->label()] = $subject->value;
        }

        return $filters
            ->add(NullFilter::new('processedAt', 'État')->setChoiceLabels('À traiter', 'Traité'))
            ->add(ChoiceFilter::new('subject', 'Objet')->setChoices($subjects));
    }

    public function configureFields(string $pageName): iterable
    {
        $subjects = [];
        foreach (ContactSubject::cases() as $subject) {
            $subjects[$subject->label()] = $subject->value;
        }

        yield DateTimeField::new('createdAt', 'Reçu le')->hideOnForm();
        yield TextField::new('name', 'Nom')->hideOnForm();
        yield EmailField::new('email', 'E-mail')->hideOnForm();
        yield ChoiceField::new('subject', 'Objet')->setChoices($subjects)->hideOnForm();
        yield BooleanField::new('processed', 'Traité')->renderAsSwitch(false)->hideOnForm();
        yield AssociationField::new('user', 'Compte')->hideOnForm()->hideOnIndex();
        yield TextareaField::new('message', 'Message')->hideOnForm()->hideOnIndex()->setTemplatePath('admin/field/multiline.html.twig');
        yield DateTimeField::new('processedAt', 'Traité le')->onlyOnDetail();
        yield AssociationField::new('processedBy', 'Par')->onlyOnDetail();
        yield TextareaField::new('note', 'Note du club')->hideOnIndex()->setHelp('Réponse apportée, qui s’en occupe… Visible du club seulement.');
    }

    #[AdminRoute('/{id}/traite', name: 'processed', options: ['methods' => ['POST']])]
    public function processed(ContactRequest $request, Request $httpRequest): Response
    {
        $this->checkToken('processed', $request, $httpRequest);
        $by = $this->getUser();
        $request->markProcessed($by instanceof User ? $by : null, $this->clock->now());
        $this->entityManager->flush();
        $this->addFlash('success', \sprintf('Message de %s marqué comme traité.', $request->getName()));

        return $this->redirectToRoute('admin_contact_request_index');
    }

    #[AdminRoute('/{id}/rouvrir', name: 'reopen', options: ['methods' => ['POST']])]
    public function reopen(ContactRequest $request, Request $httpRequest): Response
    {
        $this->checkToken('reopen', $request, $httpRequest);
        $request->reopen();
        $this->entityManager->flush();

        return $this->redirectToRoute('admin_contact_request_detail', ['entityId' => $request->getId()]);
    }

    private function checkToken(string $action, ContactRequest $request, Request $httpRequest): void
    {
        if (!$this->isCsrfTokenValid($action.$request->getId(), $httpRequest->query->getString('token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
