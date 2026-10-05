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

## Theme

From the Figma "Admin" page (node 37-3: nine screens in light and dark, and two style sheets
listing every value and its role). The theme only changes how EasyAdmin looks, never how it works:

| Layer | Where | What |
|---|---|---|
| EasyAdmin theme API | `DashboardController::configureDashboard()` → `setTheme()` | Primary color (navy `#3e5d83`, `#91b4dd` on the dark scheme), radius `md` (4px), density `md` (2px grid) |
| Aliases | `assets/styles/admin.css` §1 | The `--ep-*` variables of the style sheet, per scheme (`:root, .ea-dark-scheme` then `.ea-dark-scheme`); colors shared with the site come from `tokens.css` |
| EasyAdmin variables | `admin.css` §2 | `--body-bg`, `--sidebar-*`, `--table-*`, `--form-*`, `--badge-*`, `--alert-*`, `--button-*`... mapped to the aliases (EasyAdmin styles are in cascade layers, so plain declarations win) |
| Sizes without variable | `admin.css` §3 | Page title (Barlow 28/34), table panel and row heights, sidebar entries, badges, fieldsets as panels |
| Brand and fonts | `templates/admin/_brand.html.twig` (dashboard title), `templates/_fonts.html.twig` (shared with the site, added with `addHtmlContentToHead()`) | Glider mark + "ePlaneur", "Administration du club" |
| Icons | `assets/icons/admin/` + `Assets::useCustomIconSet('admin')` | The site's line icons: menu, actions and fieldsets use names such as `images`, `files`, `upload` |

Conventions that keep screens consistent with the mock-up:

- Group form fields in `FormField::addFieldset('Titre')` (rendered as panels) and use `setColumns(6)`
  for two fields side by side; `->addCssClass('ep-choices-columns')` puts long checkbox lists on
  two columns.
- Booleans edited in forms use `->setFormTypeOption('label_attr', ['class' => 'checkbox-switch'])`
  (a switch), with `->renderAsSwitch(false)` when the list must not toggle them in one click.
- Visibility badges: `secondary` = everyone, `info` = logged-in users, `warning` = chosen groups
  (colors of the style sheet).
- Not reproduced (EasyAdmin has no slot for them): the breadcrumb above the title, the subtitle
  under list titles, the per-list search field and the pagination inside the table panel.

## Code

| Part | Where |
|---|---|
| Dashboard, menu, shortcuts, date formats | `src/Controller/Admin/DashboardController.php` |
| One CRUD controller per entity | `src/Controller/Admin/*CrudController.php` |
| Visibility fields shared by restricted contents | `src/Controller/Admin/VisibilityFields.php` |
| Upload form | `src/Form/Admin/MediaUploadType.php`, `templates/admin/media/upload.html.twig` |
| Templates (dashboard, list fields) | `templates/admin/` |
| Theme | `assets/styles/admin.css`, `templates/admin/_brand.html.twig`, `assets/icons/admin/` (see [Theme](#theme)) |
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

Enums shown in the back-office implement `TranslatableInterface` (their French `label()`), so
EasyAdmin lists and displays them by itself: an enum-typed property only needs
`ChoiceField::new('property')`. Forms submit those enums by their value (`groups`); the
permissions of a group, stored as a JSON list, use explicit choices and are submitted by their
position in `Permission::cases()`.
