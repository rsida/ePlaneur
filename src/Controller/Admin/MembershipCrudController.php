<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Membership;
use App\Entity\User;
use App\Membership\MembershipManager;
use App\Membership\MembershipStatus;
use App\Repository\MembershipRepository;
use App\Repository\MembershipSeasonRepository;
use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Membership requests: the club records payments made outside the site, validates requests (the
 * account joins the "Membre" group and receives its certificate) or refuses them; CSV export of a
 * season for the treasurer and the federation. Requests are made by members on the site, and kept
 * for the books (no creation, no deletion here).
 *
 * @extends AbstractCrudController<Membership>
 */
#[IsGranted(Permission::MembershipManage->value)]
final class MembershipCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly MembershipManager $manager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Membership::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('adhésion')
            ->setEntityLabelInPlural('Adhésions')
            ->setDefaultSort(['requestedAt' => 'DESC'])
            ->setSearchFields(['firstName', 'lastName', 'email', 'phone', 'paymentReference']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $step = fn (string $name, string $label, string $icon): Action => Action::new($name, $label, $icon)
            ->linkToRoute('admin_membership_'.$name, fn (Membership $membership): array => [
                'id' => $membership->getId(),
                'token' => $this->csrfTokenManager->getToken($name.$membership->getId())->getValue(),
            ])
            ->renderAsForm();
        $validate = $step('validate', 'Valider', 'circle-check')->asSuccessAction()
            ->displayIf(static fn (Membership $membership): bool => \in_array($membership->getStatus(), [MembershipStatus::Requested, MembershipStatus::Paid], true));
        $paid = $step('paid', 'Paiement reçu', 'banknote')
            ->displayIf(static fn (Membership $membership): bool => MembershipStatus::Requested === $membership->getStatus());
        $refuse = $step('refuse', 'Refuser', 'x')->asDangerAction()->askConfirmation('Refuser cette demande d’adhésion ?')
            ->displayIf(static fn (Membership $membership): bool => \in_array($membership->getStatus(), [MembershipStatus::Requested, MembershipStatus::Paid], true));
        $export = Action::new('export', 'Exporter la saison (CSV)', 'download')->linkToRoute('admin_membership_export')->createAsGlobalAction();

        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $validate)->add(Crud::PAGE_DETAIL, $validate)
            ->add(Crud::PAGE_INDEX, $paid)->add(Crud::PAGE_DETAIL, $paid)
            ->add(Crud::PAGE_DETAIL, $refuse)
            ->add(Crud::PAGE_INDEX, $export);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('season', 'Saison'))
            ->add(ChoiceFilter::new('status', 'État')->setChoices(self::statusChoices()))
            ->add(EntityFilter::new('type', 'Type'));
    }

    public function configureFields(string $pageName): iterable
    {
        $statuses = ChoiceField::new('status', 'État')->setChoices(self::statusChoices())
            ->renderAsBadges(['requested' => 'warning', 'paid' => 'info', 'validated' => 'success', 'refused' => 'danger', 'cancelled' => 'secondary'])
            ->hideOnForm();

        yield FormField::addFieldset('Adhésion');
        yield TextField::new('fullName', 'Membre')->onlyOnIndex();
        yield AssociationField::new('season', 'Saison')->hideOnForm();
        yield AssociationField::new('type', 'Type');
        yield $statuses;
        yield MoneyField::new('fee', 'Cotisation')->setCurrency('EUR')->setStoredAsCents()->hideOnForm()->hideOnIndex();
        yield MoneyField::new('donation', 'Soutien volontaire')->setCurrency('EUR')->setStoredAsCents()->hideOnIndex();
        yield MoneyField::new('total', 'Total')->setCurrency('EUR')->setStoredAsCents()->hideOnForm();
        yield DateTimeField::new('requestedAt', 'Demande')->hideOnForm();

        yield FormField::addFieldset('Membre');
        yield TextField::new('firstName', 'Prénom')->hideOnIndex();
        yield TextField::new('lastName', 'Nom')->hideOnIndex();
        yield EmailField::new('email', 'E-mail')->hideOnForm();
        yield AssociationField::new('user', 'Compte')->hideOnForm()->hideOnIndex();
        yield DateField::new('birthDate', 'Date de naissance')->hideOnIndex();
        yield TextField::new('address', 'Adresse')->hideOnIndex();
        yield TextField::new('postalCode', 'Code postal')->hideOnIndex();
        yield TextField::new('city', 'Ville')->hideOnIndex();
        yield CountryField::new('country', 'Pays')->hideOnIndex();
        yield TelephoneField::new('phone', 'Téléphone')->hideOnIndex();
        yield BooleanField::new('rulesAccepted', 'Statuts et règlement acceptés')->onlyOnDetail();

        yield FormField::addFieldset('Paiement et validation');
        yield ChoiceField::new('paymentMethod', 'Moyen de paiement')->setChoices(Membership::PAYMENT_METHODS)->hideOnIndex()->hideOnForm();
        yield TextField::new('paymentReference', 'Référence du paiement')->hideOnIndex()->setHelp('Numéro du paiement en ligne, libellé du virement…');
        yield DateTimeField::new('paidAt', 'Payée le')->hideOnForm()->hideOnIndex();
        yield DateTimeField::new('validatedAt', 'Validée ou refusée le')->hideOnForm()->hideOnIndex();
        yield AssociationField::new('validatedBy', 'Par')->hideOnForm()->hideOnIndex();
        yield TextareaField::new('note', 'Note du club')->hideOnIndex()->setHelp('Visible du club seulement.');
    }

    #[AdminRoute('/{id}/valider', name: 'validate', options: ['methods' => ['POST']])]
    public function validate(Membership $membership, Request $request): Response
    {
        $this->checkToken('validate', $membership, $request);
        $by = $this->getUser();
        $this->manager->validate($membership, $by instanceof User ? $by : null);
        $this->addFlash('success', \sprintf('Adhésion de %s validée : le compte a rejoint le groupe « Membre », un e-mail de bienvenue lui a été envoyé.', $membership->getFullName()));

        return $this->redirectToRoute('admin_membership_index');
    }

    #[AdminRoute('/{id}/paiement', name: 'paid', options: ['methods' => ['POST']])]
    public function paid(Membership $membership, Request $request): Response
    {
        $this->checkToken('paid', $membership, $request);
        $this->manager->markPaid($membership, Membership::PAYMENT_TRANSFER, $membership->getPaymentReference());
        $this->addFlash('success', \sprintf('Paiement de %s enregistré : l’adhésion est à valider.', $membership->getFullName()));

        return $this->redirectToRoute('admin_membership_index');
    }

    #[AdminRoute('/{id}/refuser', name: 'refuse', options: ['methods' => ['POST']])]
    public function refuse(Membership $membership, Request $request): Response
    {
        $this->checkToken('refuse', $membership, $request);
        $by = $this->getUser();
        $this->manager->refuse($membership, $by instanceof User ? $by : null);
        $this->addFlash('success', \sprintf('Demande de %s refusée.', $membership->getFullName()));

        return $this->redirectToRoute('admin_membership_index');
    }

    /** Memberships of a season (`?saison=`, the latest by default) for the treasurer and the federation. */
    #[AdminRoute('/export', name: 'export', options: ['methods' => ['GET']])]
    public function export(Request $request, MembershipSeasonRepository $seasons, MembershipRepository $memberships): Response
    {
        $season = $seasons->findOneByYear($request->query->getInt('saison')) ?? $seasons->findOneBy([], ['year' => 'DESC']);
        if (null === $season) {
            $this->addFlash('warning', 'Aucune saison à exporter.');

            return $this->redirectToRoute('admin_membership_index');
        }
        $rows = $memberships->findBy(['season' => $season], ['lastName' => 'ASC', 'firstName' => 'ASC']);
        $response = new StreamedResponse(static function () use ($rows): void {
            $out = fopen('php://output', 'w');
            \assert(false !== $out);
            // Excel reads UTF-8 with its byte order mark; semicolons for the French locale
            fwrite($out, "\u{FEFF}");
            $euros = static fn (int $cents): string => number_format($cents / 100, 2, ',', '');
            fputcsv($out, ['Saison', 'État', 'Nom', 'Prénom', 'E-mail', 'Date de naissance', 'Adresse', 'Code postal', 'Ville', 'Pays', 'Téléphone', 'Type', 'Cotisation', 'Soutien', 'Total', 'Payée le', 'Moyen de paiement', 'Référence', 'Validée le'], ';', '"', '');
            foreach ($rows as $membership) {
                fputcsv($out, [
                    $membership->getSeason()->getYear(),
                    $membership->getStatus()->label(),
                    $membership->getLastName(),
                    $membership->getFirstName(),
                    $membership->getEmail(),
                    $membership->getBirthDate()?->format('d/m/Y'),
                    $membership->getAddress(),
                    $membership->getPostalCode(),
                    $membership->getCity(),
                    $membership->getCountry(),
                    $membership->getPhone(),
                    $membership->getType()?->getName(),
                    $euros($membership->getFee()),
                    $euros($membership->getDonation()),
                    $euros($membership->getTotal()),
                    $membership->getPaidAt()?->format('d/m/Y'),
                    $membership->getPaymentMethodLabel(),
                    $membership->getPaymentReference(),
                    $membership->getValidatedAt()?->format('d/m/Y'),
                ], ';', '"', '');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', \sprintf('attachment; filename="adhesions-%d.csv"', $season->getYear()));

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private static function statusChoices(): array
    {
        $choices = [];
        foreach (MembershipStatus::cases() as $status) {
            $choices[$status->label()] = $status->value;
        }

        return $choices;
    }

    private function checkToken(string $action, Membership $membership, Request $request): void
    {
        if (!$this->isCsrfTokenValid($action.$membership->getId(), $request->query->getString('token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
