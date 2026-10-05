# Back-office (administration)

The back-office is built with [EasyAdmin](https://symfony.com/bundles/EasyAdminBundle/current/index.html)
5 and served at `/admin`. Roadmap step 3a delivered the reference screens; posts and pages, with
their visual block editor, come with step 3b (see [roadmap](roadmap.md)).

## Access

| Who | Needs |
|---|---|
| Open `/admin` | `ADMIN_ACCESS` (Comité and Administrateur by default) |
| Each screen | Its own permission, checked with `#[IsGranted]` on the CRUD controller: hidden in the menu **and** refused by URL |

The account page (`/mon-compte`) links to the back-office for accounts holding `ADMIN_ACCESS`.
Permissions and default groups are described in [accounts](accounts.md).

## Screens

| Screen | URL | Permission | Notes |
|---|---|---|---|
| Dashboard | `/admin` | `ADMIN_ACCESS` | One card per screen the user may open |
| Catégories d'articles | `/admin/category` | `CATEGORY_MANAGE` | Name, slug (generated from the name), description |
| Médiathèque | `/admin/media` | `MEDIA_MANAGE` | "Téléverser" adds several files at once (images, PDF, text, ZIP; 20 MB each) with their visibility; alt text and credit are edited afterwards. A file used by a document or as a post cover cannot be deleted |
| Documents officiels | `/admin/document` | `DOCUMENT_MANAGE` | The file is chosen from the media library; dated mentions = one line each; the file takes the document's visibility |
| Catégories de documents | `/admin/document-category` | `DOCUMENT_MANAGE` | Name, identifier, order |
| Menus | `/admin/menu-item` | `MENU_MANAGE` | Header (3 levels) and footer links: page or address, note, order, visibility; the parent must be in the same menu |
| Comptes | `/admin/user` | `USER_MANAGE` | No creation (accounts come from registration); validating a membership = adding the "Membre" group; an administrator cannot delete their own account; passwords are never shown |
| Groupes et droits | `/admin/group` | `GROUP_MANAGE` | Permissions as checkboxes; the code is set once at creation; default groups cannot be deleted |

Pictures are served as uploaded: resized versions are an open question (see [roadmap](roadmap.md)).

## Code

| Part | Where |
|---|---|
| Dashboard, menu, shortcuts, date formats | `src/Controller/Admin/DashboardController.php` |
| One CRUD controller per entity | `src/Controller/Admin/*CrudController.php` |
| Visibility fields shared by restricted contents | `src/Controller/Admin/VisibilityFields.php` |
| Upload form | `src/Form/Admin/MediaUploadType.php`, `templates/admin/media/upload.html.twig` |
| Templates (dashboard, list fields) | `templates/admin/` |
| Colors of the site | `assets/styles/admin.css` (EasyAdmin tokens mapped to `tokens.css`) |
| Tests | `tests/Functional/Admin/` (`AdminAccessTest` + one `AbstractCrudTestCase` per screen) |

EasyAdmin ships an agent skill describing its 5.x API, installed in `.claude/skills/easyadmin/`
(`make console c="easyadmin:ai:install --agent=claude"`) and refreshed by `composer update`
(`easyadmin:ai:update` in `post-update-cmd`). Its API differs from EasyAdmin 4: read the skill
before writing admin code.

## Adding a screen

1. If the screen needs a new right, add a case to `App\Security\Permission` (and a migration
   granting it to the groups that need it).
2. Create `src/Controller/Admin/<Entity>CrudController.php`: `final`, `#[IsGranted(Permission::X->value)]`,
   `/** @extends AbstractCrudController<Entity> */`, French labels. Override `createEntity()` when
   the entity takes constructor arguments; give required text fields `empty_data: ''` so an empty
   field shows a validation message instead of a type error.
3. Add it to `DashboardController::configureMenuItems()` with `->setPermission()` and, if useful, to
   `shortcuts()`.
4. Add a test in `tests/Functional/Admin/` and the screen to the table above.

Enum choices (`Visibility`, `MenuLocation`, `Permission`) are submitted by their position in
`cases()`: tests that post forms directly must send that index.
