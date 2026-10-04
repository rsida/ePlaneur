# Content: posts, pages, menus, documents, blocks and media

How news posts, institutional pages, navigation menus and official documents are stored and
rendered, and how to add a new kind of block. The visual references are the Figma frame "Premier
vol · Article desktop" (node 29-4356), reproduced by the demo post at `/actualites/votre-premier-vol`,
and the step 2b frames (nodes 33-17798 to 33-20172: header and mega-menu, mobile menu, "Le Club"
section, "Statuts", "Autres documents", "Contenu réservé"), reproduced by the fixtures under
`/le-club`.

## Model

| Entity | Role |
|---|---|
| `Post` | Title, slug (`/actualites/{slug}`), kicker and level badge, excerpt, optional catchphrase, cover + caption, category, keywords, author, publication date, featured flag, visibility, and three block zones |
| `Category` | Name and slug of a post category |
| `Media` | Uploaded file (image, PDF, text...): stored in `var/uploads`, served by `/media/{id}/{name}` with access control |
| `User` (author fields) | `jobTitle`, `bio`, `avatar` shown in the article header and the author card |
| `Page` | Institutional page in a tree (max 4 levels): the tree gives the URL (`/le-club/textes-officiels/statuts`) and breadcrumb; kicker, excerpt, highlight + note (catchphrase on the right of the header, inherited from the nearest ancestor), link label (cards), publication date, visibility, `body` and `aside` block zones |
| `MenuItem` | Navigation link, one tree per location (`main` header, `footer` columns): target page, internal path (`/actualites`, `/#vols`), external URL or nothing (heading grouping its children); description (note under the link, or the mega-menu kicker of a first-level link); position, visibility |
| `Document`, `DocumentCategory` | Official document (statutes, internal rules, decisions...): file (Media), category, version, details (dated mentions: "Adoptés le 15/12/2025"), position, visibility |

A post is **published** when `publishedAt` is set and in the past. Drafts and scheduled posts are
only visible to users holding `POST_EDIT` (preview banner). `Post` and `Media` implement
`RestrictedContentInterface`: visibility `public`, `authenticated` or `groups` (see
[accounts](accounts.md)).

## Pages, menus and restricted content

- Pages are served by `PageController` (`/{path}`, lowest route priority). A root page cannot take a
  slug used by the application (`Page::RESERVED_SLUGS`); a page with children cannot be deleted
  (`PageDeletionGuard`). Every ancestor of a page must be visible too.
- `MenuBuilder` (Twig `menu('main')`, `menu('footer')`) builds the links the reader may see. A link is
  hidden by its own visibility or when its target page is unpublished; a link to a **reserved page
  stays visible** with an access tag (group names or "Connectés") and leads to the "Contenu réservé"
  page, so readers know the content exists. A heading whose links are all hidden disappears; the
  current section is `active`, the current link `current`.
- Header: a first-level link with children opens a mega-menu (one column per second-level link,
  their links with note and access tag); below 64em the menu is a full-screen panel with accordions.
- Page layout: a root page (section) spreads its blocks over the full width; a sub-page shows the
  pages of its section on the left, the `aside` blocks below them, and its update date. A page with
  published sub-pages lists them as cards (`child_pages` block, added at the end when the editor did
  not place one); reserved sub-pages appear with their access tag.
  The first migration of step 2b seeds the menus with the links the header and footer had in code.
- Readers without access to a page or post get the **"Contenu réservé"** page (HTTP 403,
  `RestrictedContentResponder`): login and registration buttons for visitors (back to the page after
  login), the required group for logged-in users. The header of the page or post (breadcrumb, title,
  lead) stays; only its body is replaced.
- A document's visibility is copied onto its file before each flush (`DocumentAccessListener`), so
  the file URL is exactly as restricted as the document.
- `/actualites` redirects to the news section of the home page until the news list exists (step 4).


## Block zones

| Zone | Where it is rendered |
|---|---|
| `body` | Reading column (820px; full width on a root page) |
| `aside` | Sidebar: under the table of contents (posts), under the section's pages (pages) |
| `outro` | Full-width bands after the body (takeaways) |

Each zone is a JSON list of `{"type": "...", "data": {...}}`. `BlockFactory` converts it to block
objects (`App\Content\Block\*`, immutable) with the Symfony serializer, and back
(`serializeAll()`, used by the fixtures and the future editor). A block whose type is unknown or
whose data no longer fits its class is **skipped and logged**: one broken block never breaks a page.

`PostPresenter` prepares the page: numbers the `section` and `takeaways` blocks (table of contents
and anchors), computes the reading time (200 words per minute), preloads every media in one query
and selects three related posts the reader is allowed to see.

## Block types

| Type (`BlockType`) | Content | Notes |
|---|---|---|
| `section` | eyebrow, title, intro, navTitle | Numbered ("01 / …"), entry of the table of contents |
| `text` | html, muted | Rich text; `muted` for secondary paragraphs |
| `heading` | text, size (`md`, `lg`) | Subtitle |
| `callout` | variant (`info`, `tip`, `warning`), title, html, showIcon | |
| `image` | mediaId, caption | Credit comes from the media |
| `image_grid` | images (mediaId, caption) | Pictures side by side |
| `carousel` | title, slides (mediaId, label, caption) | Arrows, thumbnails, keyboard |
| `video` | url, title, subtitle, posterId, caption | YouTube or Vimeo URL; the player loads on click (youtube-nocookie) |
| `quote` | text, attribution, reference | With a reference ("Statuts V23 / Article 2"): quotation of a reference text |
| `excerpt` | title, text, reference | Passage of a reference text under a rule |
| `list` | items (rich text), ordered | |
| `steps` | steps (title, text) | Numbered 01, 02… |
| `tabs` | tabs (label, title, html, facts, linkLabel, linkUrl) | Accessible tabs |
| `checklist` | title, items, note | Ticks kept in the reader's browser |
| `table` | headers, rows, caption | First column = row headers |
| `pdf` | mediaId, title | Browser PDF viewer, open and download links; hidden on mobile |
| `downloads` | files (mediaId, title, description), note | Format and size from the media |
| `documents` | categoryId (all when empty), title, intro, layout (`list`, `cards`) | Official documents the reader may see, grouped by category; readers switch between compact list and cards (`layout` controller) |
| `document` | documentId, linkLabel | One document in a warm card; hidden when the reader may not see it |
| `child_pages` | eyebrow | Cards of the page's published sub-pages (pages only) |
| `notes` | title, rows (label, text), note | |
| `glossary` | title, entries (label = term, text = definition) | |
| `faq` | title, items (question, answer), openFirst | Native `<details>` |
| `links` | links (title, url, description), style (`rows`, `arrows`), note | |
| `resource` | title, text, linkLabel, linkUrl | Sidebar card |
| `takeaways` | eyebrow, title, text, points, ctaLabel, ctaUrl, navTitle | Closing band, numbered like a section |

Rich text fields contain limited HTML (paragraphs, bold, italic, links, lists, code) and are always
printed through the `app.rich_text` sanitizer (`config/packages/html_sanitizer.yaml`).

## Adding a block type

1. Create the block class in `src/Content/Block/` (`final readonly`, implements `BlockInterface`,
   constructor properties = stored data; nested lists typed with `@param list<Item>`).
2. Add a case to `BlockType` (value = stored type, never renamed afterwards) with its class and label.
3. Create its component `templates/components/Block/<Name>.html.twig` (`{% props placed %}`,
   `{% set data = placed.block %}`; do not name the variable `block`, Twig reserves it inside
   components) and its styles in `assets/styles/components/content.css`.
4. Add a sample to the `/_toolkit` style guide (`ToolkitController::sampleBlocks()`).
5. `BlockFactoryTest::testEveryBlockTypeHasAComponent` fails until the component exists.

## Media

`MediaStorage::storeCopy()` copies a file into `app.uploads_dir` (`var/uploads`, `var/test-uploads` in
tests) under a random name, reads image dimensions and PDF page counts. `MediaController` serves it:

- public media: no session read, cached one year (`immutable`);
- restricted media: `CONTENT_VIEW` check, `no-store`;
- only images and PDF are displayed inline; any other type is downloaded.

Twig helpers: `media(id)`, `media_url(media, download = false)`, `|file_size` ("248 Ko"),
`reading_minutes(post)`, `document(id)`, `documents(categoryId)` (documents the reader may see).

In production `var/uploads` is the Docker volume `uploads`: back it up with the database (see
[production](production.md)).

## Permissions

| Permission | Allows |
|---|---|
| `POST_CREATE` | Writing posts |
| `POST_EDIT` | Editing every post, seeing drafts |
| `POST_PUBLISH` | Publishing or unpublishing |
| `POST_DELETE` | Deleting posts |
| `CATEGORY_MANAGE` | Managing categories |
| `MEDIA_MANAGE` | Managing the media library |
| `PAGE_MANAGE` | Managing pages, seeing unpublished pages |
| `MENU_MANAGE` | Managing navigation menus |
| `DOCUMENT_MANAGE` | Managing official documents |

The "Comité" group gets all of them except `POST_DELETE` and `MENU_MANAGE` by default (migrations
`Version20261004143458` and `Version20261004155717`); the screens using them come with the
back-office (roadmap step 3).
