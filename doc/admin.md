# Back-office (administration)

The back-office is built with [EasyAdmin](https://symfony.com/bundles/EasyAdminBundle/current/index.html)
5 and served at `/admin`. Roadmap step 3a delivered the reference screens, step 3b the posts and
pages with their visual block editor (see [roadmap](roadmap.md) for what is left of 3b).

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
| Articles | `/admin/post` | `POST_CREATE` or `POST_EDIT` | List with state badge (Brouillon, Programmé, Publié); creating and editing open the [block editor](#block-editor). `POST_CREATE` edits one's own posts only, `POST_EDIT` every post (`PostVoter`, `POST_WRITE`); `POST_PUBLISH` sets the publication date; `POST_DELETE` deletes |
| Pages | `/admin/page` | `PAGE_MANAGE` | The page tree (folding, search), not a paginated table; "Ajouter une sous-page" creates a page under another; creating and editing open the block editor. Saving sets the "Mis à jour le" date to today unless another date was chosen. A page with sub-pages cannot be deleted |
| Catégories d'articles | `/admin/category` | `CATEGORY_MANAGE` | Name, slug (generated from the name), description |
| Médiathèque | `/admin/media` | `MEDIA_MANAGE` | "Téléverser" adds several files at once (images, PDF, text, ZIP; 20 MB each) with their visibility; alt text and credit are edited afterwards. A file used by a document or as a post cover cannot be deleted |
| Documents officiels | `/admin/document` | `DOCUMENT_MANAGE` | The file is chosen from the media library; dated mentions = one line each; the file takes the document's visibility |
| Catégories de documents | `/admin/document-category` | `DOCUMENT_MANAGE` | Name, identifier, order |
| Menus | `/admin/menu-item` | `MENU_MANAGE` | Header (3 levels) and footer links: page or address, note, order, visibility; the parent must be in the same menu |
| Comptes | `/admin/user` | `USER_MANAGE` | No creation (accounts come from registration); validating a membership = adding the "Membre" group; an administrator cannot delete their own account; passwords are never shown |
| Groupes et droits | `/admin/group` | `GROUP_MANAGE` | Permissions as checkboxes; the code is set once at creation; default groups cannot be deleted |

Pictures are displayed through resized versions (see [content](content.md#resized-pictures)).

## Block editor

Posts and pages are edited in a WordPress-like editor (Figma "Admin" page, frames `71:3` to
`71:2740`) that replaces EasyAdmin's form pages (`new()` and `edit()` of `PostCrudController` and
`PageCrudController`):

| Part | What it does |
|---|---|
| Top bar | Back to the list, title and "Modifications non enregistrées" / "Enregistré", state badge, undo / redo, "Aperçu" (the site page with the unsaved changes, new tab), "Enregistrer" (Ctrl + S), "Publier" (dialog: now, or at a date and time in metropolitan France time; "Repasser en brouillon") |
| Left panel | "Blocs": the block library by group (Texte, Médias, Mise en forme, Club) with a search; click inserts after the selected block, drag and drop inserts where the line shows. "Structure": the blocks of each zone, click to select |
| Canvas | An iframe showing the content **with the site styles**: header and blocks rendered by the site components. Titles and texts are edited in place; the dashed "+" adds a text block at the end of a zone; typing "/" in an empty text block opens the block search (↑ ↓, Entrée, Échap) |
| Block toolbar | Above the selected block: grip (drag to move), type, up, down, bold / italic / link / list for rich text, "…" (Dupliquer, Insérer avant, Insérer après, Supprimer) |
| Right panel | "Article" / "Page": the settings of the content (address, category, cover, visibility...). "Bloc": the settings of the selected block that are not edited in place |
| Keyboard | Ctrl + Z / Ctrl + Maj + Z outside a text, Alt + Maj + ↑ / ↓ moves the selected block, Échap leaves the selection |
| Tablets (below 75em, 1200 px) | The canvas takes the whole width; "Bibliothèque" and "Réglages" open the side panels over it (Figma "Tablette · …"); Échap or a click on the veil closes them |

How it works:

- The controller `block-editor` (`assets/editor/`, entry point `assets/admin.js`) keeps the blocks of
  every zone and the undo history. After each change it writes the zones into hidden form fields
  and posts the form to the `render` action, which returns the header, the table of contents and
  one HTML fragment per block (`App\Content\Editor\CanvasRenderer`). Nothing is saved before
  "Enregistrer".
- The canvas document comes from the `canvas` action (`templates/admin/editor/canvas_*.html.twig`):
  the site entry point (styles and behaviours) plus `styles/editor-canvas.css`. Its zones are the
  zones of the content: `body`, `aside`, `outro` for a post, `body`, `aside` for a page. A page's
  layout depends on its parent: changing the parent reloads the canvas.
- Values edited in place are marked in the block components with `edit_field()` (see
  [content](content.md#adding-a-block-type)); the settings panel is built from the `#[Field]`
  attributes of the block classes (`App\Content\Editor\BlockSchema`, given to the page as JSON).
- On save, `BlockZoneType` reads every block through its class (`ZoneNormalizer`): unknown types or
  data that do not fit are refused with "Bloc n (Type) : contenu invalide.", editor ids and unknown
  keys are dropped. Rich text is cleaned with the `app.rich_text` sanitizer (so the editor can show
  stored HTML as is), and link addresses (`url` fields) must start with `/`, `#`, `http(s)://` or
  `mailto:` (`App\Content\LinkUrl`, shared with the menus): a `javascript:` link is refused.
- Media are chosen in the **"Choisir un média" window** (`assets/editor/media_picker.js`,
  `templates/admin/editor/_media_dialog.html.twig`), opened by the media fields of the settings
  panel, the post cover (`MediaPickerType`), the empty media blocks of the canvas ("Choisir dans la
  médiathèque", "Téléverser") and the toolbar of a media block. "Médiathèque" tab: search on name,
  alt text and credit, type filter (forced by the field: images, PDF), 48 files per page, details of
  the selected file; alt text and credit are saved in the library as soon as they change.
  "Téléverser" tab: drop zone or file choice, same formats and size as the media library (20 MB);
  uploaded files are visible by everyone until restricted in the media library. Changing the library
  (upload, alt text, credit) needs `MEDIA_MANAGE`; without it the window only lets users choose.
- The data come from `EditorDataController`: `/admin/editor/media` (list, `q`, `type`, `offset`),
  `/admin/editor/media/{id}` (GET one, POST alt text and credit), `/admin/editor/media/upload`, and
  `/admin/editor/documents`, `/admin/editor/document-categories` for the document blocks. The
  window's POST requests carry the `editor_media` CSRF token in an `X-CSRF-Token` header.
- Back-office forms use the stateless CSRF tokens of the site: `assets/admin.js` loads
  `csrf_protection_controller.js`.

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
| Block editor | `src/Content/Editor/` (schema, canvas rendering, zone normalizer), `src/Form/Admin/{Post,Page}EditorType.php`, `BlockZoneType.php`, `MediaPickerType.php`, `src/Controller/Admin/EditorDataController.php`, `templates/admin/editor/`, `assets/editor/`, `assets/styles/editor.css`, `assets/styles/editor-canvas.css` |
| Page tree | `templates/admin/page/tree.html.twig`, `assets/editor/page_tree_controller.js` |
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
