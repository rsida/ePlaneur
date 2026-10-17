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
| Articles | `/admin/post` | `POST_CREATE` or `POST_EDIT` | List with state badge (Brouillon, Programmé, Publié) and "À la une" column; "État" filter (`Filter\PublicationStateFilter`) and "À la une" filter; creating and editing open the [block editor](#block-editor). `POST_CREATE` edits one's own posts only, `POST_EDIT` every post (`PostVoter`, `POST_WRITE`); `POST_PUBLISH` sets the publication date; `POST_DELETE` deletes |
| Pages | `/admin/page` | `PAGE_MANAGE` | The page tree (folding, search), not a paginated table; "Ajouter une sous-page" creates a page under another; creating and editing open the block editor. Saving sets the "Mis à jour le" date to today unless another date was chosen. A page with sub-pages cannot be deleted |
| Catégories d'articles | `/admin/category` | `CATEGORY_MANAGE` | Name, slug (generated from the name), description |
| Médiathèque | `/admin/media` | `MEDIA_MANAGE` | "Téléverser" adds several files at once (images, PDF, text, ZIP: 20 MB each; MP4 and WebM videos: 200 MB each) with their visibility; alt text and credit are edited afterwards. A file used by a document or as a post cover cannot be deleted |
| Documents officiels | `/admin/document` | `DOCUMENT_MANAGE` | The file is chosen from the media library; dated mentions = one line each; the file takes the document's visibility |
| Catégories de documents | `/admin/document-category` | `DOCUMENT_MANAGE` | Name, identifier, order |
| Menus | `/admin/menu-item` | `MENU_MANAGE` | Header (3 levels) and footer links: page or address, note, order, visibility; the parent must be in the same menu |
| Adhésions | `/admin/membership` | `MEMBERSHIP_MANAGE` | Requests made from the member space (`/mon-compte/adhesion`) (no creation, no deletion): "Paiement reçu", "Valider" (adds the "Membre" group, welcome e-mail), "Refuser"; filters by season, state and type; "Exporter la saison (CSV)"; the number of requests to process shows in the menu (see [membership](membership.md)) |
| Membres | `/admin/member` | `MEMBERSHIP_MANAGE` | Read only: search by name, e-mail, CN or Condor pilot name; filters by group and by membership of the current year (`Filter\MembershipStateFilter`: validated, pending, none); the record of a member (`admin/member/detail.html.twig`) gathers their Condor identity, memberships, logbook figures and latest 30 flights with their session; "Modifier le compte" opens "Comptes" for `USER_MANAGE` |
| Saisons et tarifs | `/admin/membership-season` | `MEMBERSHIP_MANAGE` | Year, requests open or not, payment instructions, membership types with price and age limit; not deletable |
| Sessions | `/admin/network-session` | `SESSION_MANAGE` | Sessions proposed on the site or imported from eplaneur.fr; no creation here ("Proposer une session" opens the form of the site, where results are added); date, briefing, flight plan, dedicated group, organiser, Condor Club circuit, stop join, notes; filters by date, flight plan, open or dedicated, with or without results; deleting keeps the tracks (see [sessions](sessions.md)) |
| Plans de vol | `/admin/flight-plan` | `SESSION_MANAGE` | Code, route, gliders, landscape, address of the sheet on eplaneur.fr; recorded automatically, corrected here; a flight plan used by a session cannot be deleted |
| Atelier plans de vol | `/plans-de-vol/atelier` | `FLIGHT_PLAN_PREPARE` | Link to the workshop of the site, which composes the instructions to give Claude for a Condor 3 flight plan; no group has the permission by default: administrators only (see [sessions](sessions.md#flight-plan-workshop)) |
| Activité du club | `/activite` | `ACTIVITY_VIEW` | Link to the page of the site: accounts, memberships, flights, sessions and training month by month, CSV export for the general meeting (see [activity](activity.md)) |
| Comptes | `/admin/user` | `USER_MANAGE` | No creation (accounts come from registration); memberships are validated in "Adhésions" (accounts of the Yapla years: add the "Membre" group by hand); an administrator cannot delete their own account; passwords are never shown; the name shown to the others and the pseudonym ([name shown](accounts.md#name-shown)), for a minor at the request of a parent |
| Classes | `/admin/school-class` | `USER_MANAGE` | The classes of the teachers, created by them in "Mes classes" (no creation here): name, school, teacher, pupils, link open or closed; deleting a class keeps the accounts (see [teacher space](teacher-space.md)) |
| Messages de contact | `/admin/contact-request` | `CONTACT_MANAGE` | Messages sent with the contact form (`/contact`, `/mon-compte/contact`), no creation: "Marquer comme traité" / "Rouvrir", an internal note; filters by state ("À traiter", "Traité") and subject; the number of messages to process shows in the menu. Each new message is also e-mailed to `CONTACT_EMAIL`, with the sender as reply-to |
| Mon parcours | `/admin/path-step` | `TRAINING_MANAGE` | The eight steps of "Mon parcours" (no creation, no deletion): "Ouverte" (the path stops at the first step not open), introduction, items in their order (reading, exercise on a flight plan, quiz, trainer's validation) (see [Mon parcours](training-path.md)) |
| Quiz | `/admin/quiz` | `TRAINING_MANAGE` | Questions with their answers one per line, the number of the right one and an explanation; pass mark |
| Groupes et droits | `/admin/group` | `GROUP_MANAGE` | Permissions as checkboxes; "Inclut les groupes" (its members get the access and rights of those groups: Comité includes Membre); the code is set once at creation; default groups cannot be deleted |

Pictures are displayed through resized versions (see [content](content.md#resized-pictures)).

## Block editor

Posts and pages are edited in a WordPress-like editor (Figma "Admin" page, frames `71:3` to
`71:2740`) that replaces EasyAdmin's form pages. `PostCrudController` and `PageCrudController`
extend `AbstractEditorCrudController` (`new()`, `edit()`, saving, canvas rendering, preview); their
forms share `EditorFields` (header texts, slug, catchphrase, publication date, block zones) and
`AccessFields`. The author of a post is only chosen with `POST_EDIT`. The preview banner says
whether the content is not published yet or only shows unsaved changes:

| Part | What it does |
|---|---|
| Top bar | Back to the list, title and "Modifications non enregistrées" / "Enregistré", state badge, undo / redo, "Aperçu" (the site page with the unsaved changes, new tab), "Enregistrer" (Ctrl + S), named "Mettre à jour" on published content, whose status warns that saving changes the site at once, "Publier" (dialog: now, or at a date and time in metropolitan France time; "Repasser en brouillon") |
| Left panel | "Blocs": the block library by group (Texte, Médias, Mise en forme, Club) with a search; click inserts after the selected block, drag and drop inserts where the line shows. "Structure": the blocks of each zone, click to select |
| Canvas | An iframe showing the content **with the site styles**: header and blocks rendered by the site components. Titles and texts are edited in place; the dashed "+" adds a text block at the end of a zone; typing "/" in an empty text block opens the block search (↑ ↓, Entrée, Échap) |
| Block toolbar | Above the selected block: grip (drag to move), type, up, down, bold / italic / link / list for rich text, "…" (Dupliquer, Insérer avant, Insérer après, Supprimer) |
| Right panel | "Article" / "Page": the settings of the content (address, category, cover, and "Accès": "Ouvert à" and "Pour les autres", Annoncé or Privé...). "Bloc": the settings of the selected block that are not edited in place |
| Keyboard | Ctrl + Z / Ctrl + Maj + Z outside a text, Alt + Maj + ↑ / ↓ moves the selected block, Échap leaves the selection. The format buttons work from the keyboard (the selection is kept); the "…" menu takes the focus (↑ ↓ Début Fin, Échap back to its button); the block search is a combobox (`aria-activedescendant`) |
| Tablets (below 75em, 1200 px) | The canvas takes the whole width; "Bibliothèque" and "Réglages" open the side panels over it (Figma "Tablette · …"), with the focus inside and the canvas inert; Échap or a click on the veil closes them, Échap gives the focus back to the opening button |
| Errors | A refused save shows a banner whose messages link to their field; the editor opens the tab, the panel and the folded group of the first wrong field |
| Browser backup | Unsaved changes are kept in `localStorage` (one entry per editor address) until the form is submitted; on the next visit a banner offers "Les restaurer" or "Les abandonner" |

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
  "Téléverser" tab: drop zone or file choice, same formats and sizes as the media library (20 MB, 200 MB for a video);
  uploaded files take the access of the edited content ("Comme ce contenu", default) or are open to
  everyone ("Tout le monde"); the choice is only offered when the content is restricted. Changing the library
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
- Access fields of restricted contents come from `VisibilityFields::create()` ("Accès" fieldset:
  "Ouvert à", groups, "Pour les autres" Annoncé / Privé; menu links without the last one). Badges:
  `secondary` = everyone, `info` = logged-in users, `warning` = chosen groups (colors of the style
  sheet).
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
