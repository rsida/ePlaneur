# WordPress import

`app:import-wordpress` copies the content of the current site (club.eplaneur.fr, WordPress) into the
new one through the WordPress REST API: pages, posts, the files they use, and permanent redirects
of the old addresses. It can be run again at any time: content is updated, not duplicated.

## Run it

```bash
# 1. See what would happen: nothing is downloaded nor saved
make console c="app:import-wordpress --dry-run"

# 2. Import (or update) everything
make console c="app:import-wordpress"

# Only pages, or only posts
make console c="app:import-wordpress --only=posts"
```

The command prints what it created or updated and a list of points to review (reserved content it
could not read, WordPress elements it could not convert, missing parent pages...).

Reserved content (committee minutes, committee pages) is hidden by the anonymous API, which only
returns "Contenu restreint". To import it, create an **application password** on a WordPress
administrator account (*Profil › Mots de passe d'application*) and put it in `.env.local`, never in
`.env`:

```dotenv
WORDPRESS_USER=admin-login
WORDPRESS_APP_PASSWORD="abcd efgh ijkl mnop qrst uvwx"
```

Without them, reserved content is left out (listed in the report) and the rest is imported.

## Where things go

`config/wordpress/import.yaml` is the single place to decide it:

| Key | Content |
|---|---|
| `pages` | Every WordPress page (by its path) → its path in the new tree, `{ redirect: /path }` (not imported, the old address redirects there: account pages, news lists), or `skip`. The import stops on a page that is not listed, so a page created later in WordPress is never lost silently |
| `categories` | Every WordPress category → name and slug of the new category. Posts without category (committee minutes) go to `uncategorized` |
| `restricted_groups` | Groups that may read content the anonymous API hides (default: `committee`) |

The new tree follows the menus of the new site ("Le Club" with `textes-officiels`,
`listes-des-membres`, `activites/comptes-rendus`; "Formation" also holds the visitor guide
"Découvrir" and "Bien démarrer"). Edit the file and run the import again to move a page.

## What is imported

| WordPress | New site |
|---|---|
| Page | Page at its new path. A page that already exists there keeps its title (menus and breadcrumbs) and its excerpt when WordPress has none; its body is replaced |
| Post | Post `/actualites/{slug}` (a slug already taken gets the WordPress id appended), category, "À la une" for sticky posts, cover from the featured image |
| Dates | Publication and update dates (WordPress and the site share the Europe/Paris time zone) |
| Excerpt | The excerpt written in WordPress; the one WordPress makes from the start of the content is left out (posts then take their first paragraph) |
| Reserved content | Open to the groups of `restricted_groups`, **private** to the others (listed nowhere, address not found) |
| Files (pictures, PDF, MP4...) used by the content | Media library, once per address; for a resized picture (`photo-1024x576.jpg`) the original is taken. A file only used by reserved content is reserved too |
| Content | Blocks, see below |

Not imported: comments, authors (posts have no author), the WordPress menus (the new site has its
own), forms (the WPForms contact form of "Contact"), dynamic lists (latest posts, categories).

### HTML to blocks

`App\WordPress\HtmlConverter` reads the HTML of each page or post (Gutenberg blocks and
Shortcodes Ultimate spoilers):

| WordPress | Block |
|---|---|
| `h1`, `h2` | Section (numbered, in the table of contents) |
| `h3` to `h6` | Sub-title (`h3` large) |
| Paragraphs, lists, preformatted text | Text, consecutive ones merged; a separator (`hr`) starts a new one |
| Image | Image with its caption; alt text kept on the media |
| Table | Table, the first row as column titles |
| File | Files to download, consecutive ones merged |
| YouTube or Vimeo embed | Video |
| Uploaded video or audio (MP4, MP3...) | Files to download (the site does not host players) |
| Spoiler, accordion, `details` | Questions (FAQ); a spoiler holding headings or pictures is shown open: its title as a sub-title, then its content |
| Quote | Quote |
| Buttons | Links |
| Columns, groups | Their content, in order |

Links to the old site are rewritten to the new addresses; links to files point to the imported
media. Rich text is cleaned by the `app.rich_text` sanitizer, like everything the editor saves.

## Redirects

Every old address that changed gets a permanent redirect (301) in the `redirect` table:

| Old address | New address |
|---|---|
| Moved page `/le-club/documents-officiels/statuts/` | `/le-club/textes-officiels/statuts` |
| Post `/2025/03/11/trouver-un-thermique/` | `/actualites/trouver-un-thermique` |
| Category `/category/club/` | `/actualites?categorie=vie-du-club` |
| Account pages `/login/`, `/register/`... | `/connexion`, `/inscription`... |
| File `/wp-content/uploads/2026/02/Statuts.pdf` | `/media/{id}/statuts.pdf` |

`App\EventListener\LegacyRedirectListener` follows them: before routing for addresses ending with a
slash (as WordPress ones do, in one hop), otherwise when the page is not found. The query string is
kept.

## In production

Run the import once the new site is deployed and before the domain moves to it, then again just
before the switch to catch the latest changes. The files go into the `uploads` volume: back it up
with the database (see [production](production.md)).
