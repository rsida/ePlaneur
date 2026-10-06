<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Permission;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Theme;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

/**
 * Back-office entry point (/admin). Open to groups holding ADMIN_ACCESS; each screen also needs its
 * own permission (USER_MANAGE, MEDIA_MANAGE...), checked on its CRUD controller and used here to hide
 * the menu entries the user cannot open.
 *
 * Theme (Figma "Admin" page, node 37-3): EasyAdmin's own theme API sets the primary colors, radius and
 * density; assets/styles/admin.css maps the rest of the palette onto EasyAdmin's CSS variables.
 * Icons are the site's line icons (assets/icons/admin/, rendered by Symfony UX Icons).
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted(Permission::AdminAccess->value)]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'shortcuts' => array_filter(self::shortcuts(), fn (array $shortcut): bool => $this->isGranted($shortcut['permission'])),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->twig->render('admin/_brand.html.twig'))
            ->setFaviconPath('icons/glider.svg')
            ->setLocales(['fr'])
            ->setTheme(Theme::new()
                ->primaryColor('#3e5d83', '#91b4dd') // navy; lighter navy on the dark scheme
                ->radius('md')                      // 4px controls (panels: 6px in admin.css)
                ->spacing('md'));                   // 2px grid, the density of the mock-up
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
        return Assets::new()
            ->addHtmlContentToHead($this->twig->render('_fonts.html.twig'))
            ->addCssFile('styles/admin.css')
            ->addAssetMapperEntry('admin')
            ->useCustomIconSet('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'layout-dashboard');

        yield MenuItem::section('Contenus');
        yield MenuItem::linkTo(PostCrudController::class, 'Articles', 'file-text')
            ->setPermission(new Expression('is_granted("POST_CREATE") or is_granted("POST_EDIT")'));
        yield MenuItem::linkTo(PageCrudController::class, 'Pages', 'copy')
            ->setPermission(Permission::PageManage->value);
        yield MenuItem::linkTo(CategoryCrudController::class, 'Catégories d’articles', 'tags')
            ->setPermission(Permission::CategoryManage->value);
        yield MenuItem::linkTo(MediaCrudController::class, 'Médiathèque', 'images')
            ->setPermission(Permission::MediaManage->value);
        yield MenuItem::linkTo(DocumentCrudController::class, 'Documents officiels', 'files')
            ->setPermission(Permission::DocumentManage->value);
        yield MenuItem::linkTo(DocumentCategoryCrudController::class, 'Catégories de documents', 'folder')
            ->setPermission(Permission::DocumentManage->value);
        yield MenuItem::linkTo(MenuItemCrudController::class, 'Menus', 'list-tree')
            ->setPermission(Permission::MenuManage->value);

        yield MenuItem::section('Comptes');
        yield MenuItem::linkTo(UserCrudController::class, 'Comptes', 'users')
            ->setPermission(Permission::UserManage->value);
        yield MenuItem::linkTo(GroupCrudController::class, 'Groupes et droits', 'shield-check')
            ->setPermission(Permission::GroupManage->value);

        yield MenuItem::section();
        yield MenuItem::linkToRoute('Voir le site', 'external-link', 'app_home');
    }

    /**
     * Cards of the dashboard home, one per screen.
     *
     * @return list<array{label: string, text: string, icon: string, route: string, permission: string}>
     */
    private static function shortcuts(): array
    {
        return [
            ['label' => 'Articles', 'text' => 'Écrire, mettre en forme et publier les actualités.', 'icon' => 'file-text', 'route' => 'admin_post_index', 'permission' => Permission::PostCreate->value],
            ['label' => 'Pages', 'text' => 'Les pages du site, rangées en arborescence.', 'icon' => 'copy', 'route' => 'admin_page_index', 'permission' => Permission::PageManage->value],
            ['label' => 'Médiathèque', 'text' => 'Téléverser des images et des PDF, régler leur visibilité.', 'icon' => 'images', 'route' => 'admin_media_index', 'permission' => Permission::MediaManage->value],
            ['label' => 'Documents officiels', 'text' => 'Statuts, règlement, décisions : version, mentions datées, visibilité.', 'icon' => 'files', 'route' => 'admin_document_index', 'permission' => Permission::DocumentManage->value],
            ['label' => 'Menus', 'text' => 'Liens de l’en-tête et du pied de page, réservés ou non.', 'icon' => 'list-tree', 'route' => 'admin_menu_item_index', 'permission' => Permission::MenuManage->value],
            ['label' => 'Catégories d’articles', 'text' => 'Classer les actualités.', 'icon' => 'tags', 'route' => 'admin_category_index', 'permission' => Permission::CategoryManage->value],
            ['label' => 'Comptes', 'text' => 'Valider les adhésions en ajoutant le groupe « Membre ».', 'icon' => 'users', 'route' => 'admin_user_index', 'permission' => Permission::UserManage->value],
            ['label' => 'Groupes et droits', 'text' => 'Choisir ce que chaque groupe peut faire.', 'icon' => 'shield-check', 'route' => 'admin_group_index', 'permission' => Permission::GroupManage->value],
        ];
    }
}
