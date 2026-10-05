<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Back-office entry point (/admin). Open to groups holding ADMIN_ACCESS; each screen also needs its
 * own permission (USER_MANAGE, MEDIA_MANAGE...), checked on its CRUD controller and used here to hide
 * the menu entries the user cannot open.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted(Permission::AdminAccess->value)]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'shortcuts' => array_filter(self::shortcuts(), fn (array $shortcut): bool => $this->isGranted($shortcut['permission'])),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('ePlaneur · Administration')
            ->setFaviconPath('icons/glider.svg')
            ->setLocales(['fr']);
    }

    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setDateFormat('dd/MM/yyyy')
            ->setDateTimeFormat('dd/MM/yyyy HH:mm')
            ->setTimezone('Europe/Paris')
            ->setPaginatorPageSize(30);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('styles/admin.css');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-gauge');

        yield MenuItem::section('Contenus');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Catégories d’articles', 'fa fa-tags')
            ->setPermission(Permission::CategoryManage->value);
        yield MenuItem::linkTo(MediaCrudController::class, 'Médiathèque', 'fa fa-photo-film')
            ->setPermission(Permission::MediaManage->value);
        yield MenuItem::linkTo(DocumentCrudController::class, 'Documents officiels', 'fa fa-file-lines')
            ->setPermission(Permission::DocumentManage->value);
        yield MenuItem::linkTo(DocumentCategoryCrudController::class, 'Catégories de documents', 'fa fa-folder')
            ->setPermission(Permission::DocumentManage->value);
        yield MenuItem::linkTo(MenuItemCrudController::class, 'Menus', 'fa fa-bars')
            ->setPermission(Permission::MenuManage->value);

        yield MenuItem::section('Comptes');
        yield MenuItem::linkTo(UserCrudController::class, 'Comptes', 'fa fa-user')
            ->setPermission(Permission::UserManage->value);
        yield MenuItem::linkTo(GroupCrudController::class, 'Groupes et droits', 'fa fa-users')
            ->setPermission(Permission::GroupManage->value);

        yield MenuItem::section();
        yield MenuItem::linkToRoute('Voir le site', 'fa fa-arrow-up-right-from-square', 'app_home');
    }

    /**
     * Cards of the dashboard home, one per screen.
     *
     * @return list<array{label: string, text: string, icon: string, route: string, permission: string}>
     */
    private static function shortcuts(): array
    {
        return [
            ['label' => 'Médiathèque', 'text' => 'Téléverser des images et des PDF, régler leur visibilité.', 'icon' => 'fa fa-photo-film', 'route' => 'admin_media_index', 'permission' => Permission::MediaManage->value],
            ['label' => 'Documents officiels', 'text' => 'Statuts, règlement, décisions : version, mentions datées, visibilité.', 'icon' => 'fa fa-file-lines', 'route' => 'admin_document_index', 'permission' => Permission::DocumentManage->value],
            ['label' => 'Menus', 'text' => 'Liens de l’en-tête et du pied de page, réservés ou non.', 'icon' => 'fa fa-bars', 'route' => 'admin_menu_item_index', 'permission' => Permission::MenuManage->value],
            ['label' => 'Catégories d’articles', 'text' => 'Classer les actualités.', 'icon' => 'fa fa-tags', 'route' => 'admin_category_index', 'permission' => Permission::CategoryManage->value],
            ['label' => 'Comptes', 'text' => 'Valider les adhésions en ajoutant le groupe « Membre ».', 'icon' => 'fa fa-user', 'route' => 'admin_user_index', 'permission' => Permission::UserManage->value],
            ['label' => 'Groupes et droits', 'text' => 'Choisir ce que chaque groupe peut faire.', 'icon' => 'fa fa-users', 'route' => 'admin_group_index', 'permission' => Permission::GroupManage->value],
        ];
    }
}
