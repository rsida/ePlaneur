# Roadmap

Planned order of work for the rebuild of club.eplaneur.fr. Update the status column as steps are
done, and record decisions in the "Decisions" section below. Proposed on 2026-10-04, not
yet validated step by step by the project owner.

## Done

| Step | Result |
|---|---|
| Symfony 8.1 project, Docker dev/prod environment | See [installation](installation.md), [production](production.md) |
| Functional reference of the club and current site | [project/](project/README.md) |
| Design system and home page from the Figma mock-up | [design-system.md](design-system.md), `/_toolkit`, home page with placeholder content |
| Step 1: accounts, groups and permissions | [accounts.md](accounts.md): registration with e-mail confirmation, login, password reset, groups and permissions in database, `CONTENT_VIEW` voter, console commands |
| Step 2a: posts and content blocks | [content.md](content.md): `Post`, `Category`, `Media`, 22 block types, article page `/actualites/{slug}`, demo article in fixtures |
| Step 2b: pages, menus, documents | [content.md](content.md#pages-menus-and-restricted-content): page tree, menus filtered by rights (mega-menu, mobile full-screen menu), official documents (list and cards), "Contenu réservé" page, layouts from the Figma mock-up |
| Step 3a: back-office reference screens | [admin.md](admin.md): EasyAdmin at `/admin`, accounts, groups and permissions, categories, media library (upload), official documents, menus |
| Step 3b-1: block editor | [admin.md](admin.md#block-editor): post list, page tree, WordPress-like editor (canvas with the site styles, library with drag and drop, "/" command, toolbar, settings panel, undo, publication, preview) |
| Step 3b-2: media window | [admin.md](admin.md#block-editor): "Choisir un média" window (library with search and type filter, alt text and credit, upload) for block media and post covers |
| Step 3b-3: resized pictures, tablets | [content.md](content.md#resized-pictures): WebP versions made with LiipImagine and served with access control; editor panels as overlays on tablets |
| Step 4: public news | [content.md](content.md#news-list-and-home-page): `/actualites` list (featured post, category and period filters, pagination, reserved posts with their access tag), home "Ça bouge au club" section from the real posts |

## Next: content and editorial back-office (replaces the WordPress blog and pages)

Do the steps in this order: each one depends on the previous ones.

| # | Step | Content | Status |
|---|---|---|---|
| 1 | Accounts and roles | `User` entity, login, free registration, roles visitor / registered user / member / committee / admin as described in [club.md](project/club.md); a voter that decides who can read a given content | Done (2026-10-04), see [accounts.md](accounts.md) |
| 2a | Posts and content blocks | `Post`, `Category`, `Media` (uploads served with access control); article body = ordered list of typed blocks (JSON) covering every block of the Figma article (node 29-4356): text, heading, callout, image, image pair, carousel, video, quote, list, steps, tabs, checklist, table, PDF reader, downloads, notes, glossary, FAQ, links, sidebar resource, takeaways; article page (`/actualites/{slug}`) with table of contents, reading progress, sharing, author, related posts; demo article in fixtures; post permissions | Done (2026-10-04), see [content.md](content.md) |
| 2b | Pages, menus, documents | `Page` (tree giving URLs and breadcrumbs), `MenuItem` (separate tree: links to a page, an internal path or an external URL, filtered with `CONTENT_VIEW`), `Document` + `DocumentCategory` (official texts with version and date); each implements `RestrictedContentInterface`; "Contenu réservé" page for pages and posts; matching permissions (`PAGE_MANAGE`, `MENU_MANAGE`, `DOCUMENT_MANAGE`). Layouts from the Figma frames generated with the prompt in [figma-prompts.md](figma-prompts.md) | Done (2026-10-04), see [content.md](content.md#pages-menus-and-restricted-content) |
| 3a | Back-office: reference screens | EasyAdmin 5 at `/admin` (`ADMIN_ACCESS`), one screen per permission: accounts (membership validation by group), groups and their permissions, post categories, media library with multi-file upload, official documents and their categories, menus | Done (2026-10-05), see [admin.md](admin.md) |
| 3b | Back-office: posts and pages | Post and page screens with a **visual block editor** (WordPress-like: blocks rendered with the site styles, text edited in place, a settings panel per block, add / move / delete blocks), publication and preview, media picker, resized images. Mock-up: Figma "Admin" page, frames `71:3` to `71:2740` | Done (2026-10-06), split below |
| 3b-1 | Editor core | Post list (state badges) and page tree; editor page: canvas in an iframe with the site styles, block library (drag and drop, search), "/" command, toolbar (move, duplicate, insert, delete, bold / italic / link / list), settings panel generated from the block classes, structure tab, undo, save, publish or schedule, preview | Done (2026-10-05), see [admin](admin.md#block-editor) |
| 3b-2 | Media picker | Media window (search, type filter, upload, alt text and credit) for covers and image, carousel, PDF, downloads, video poster blocks | Done (2026-10-06), see [admin](admin.md#block-editor) |
| 3b-3 | Images and finish | Resized images (LiipImagineBundle), tablet layout (panels as overlays), dark mode check | Done (2026-10-06), see [content](content.md#resized-pictures) and [admin](admin.md#block-editor) |
| 4 | Public front | News list (replaces the `/actualites` redirect), post page, filter by category and date; the home page "Ça bouge au club" section reads featured posts instead of placeholders; pages rendered from the tree with the menu (done in 2b) | Done (2026-10-06), see [content](content.md#news-list-and-home-page) |
| 5 | WordPress migration | `app:import-wordpress` command reading posts, pages and media through the WordPress REST API (79 pages, 34 posts, about 20 of them restricted committee minutes); 301 redirects from the old URLs | In progress (2026-10-07): command, page tree mapping and redirects done, see [wordpress-import](wordpress-import.md); the reserved content waits for a WordPress application password |

Why this order: restricted content (committee minutes, 4th-level pages) and the back-office both need
accounts and roles first; EasyAdmin gives a usable back-office quickly, a custom admin is only worth it
later for business screens (network flights, progression tracking); the import avoids retyping the
existing content.

## Audit follow-ups (2026-10-07)

The code, JS/CSS and UX audit of 2026-10-07 found five urgent issues, fixed the same day: unsafe
`javascript:` links in blocks, rich text not cleaned on save, a test tied to a calendar date, arrow
keys handled twice in the block menu, a low-contrast focus ring. Left for later, in this order:

| Batch | Content |
|---|---|
| Editor UX | Warn that saving a published post updates the site at once; open the tab and highlight the field of a validation error; choose the visibility of files uploaded from the editor; state filter and "À la une" column in the post list; keyboard and ARIA for the block menus and format buttons; focus management of the tablet panels and of the site's mobile menu; draft backup in the browser |
| Code | One base controller and shared form fields for the post and page editors; one access-label service; N+1 queries on the groups of reserved content; author field only with POST_EDIT; unpublished sections hide their sub-pages; editor preview banner only for unpublished content; slug length and page number validation; `ClockInterface` instead of hard-coded `Europe/Paris`; tests for the category and menu screens |
| CSS | Split `content.css` into one file per component; semantic tokens instead of `--palette-*` and literal sizes; one set of breakpoints; merge `c-news--featured` into `c-featured-post`; self-hosted fonts instead of Google Fonts |

## Decisions

| Date | Decision |
|---|---|
| 2026-10-04 | Rights = permissions (fixed catalogue in code) held by groups stored in database; a user belongs to several groups; administrators edit groups and their permissions. Content visibility = public, logged-in users, or chosen groups |
| 2026-10-04 | Free registration requires e-mail confirmation; password reset by e-mail |
| 2026-10-04 | Content bodies are an ordered list of typed blocks (CMS-like), stored as JSON; rich text inside blocks is limited HTML (bold, italic, links), sanitized |
| 2026-10-04 | Videos are YouTube (youtube-nocookie) or Vimeo URLs, loaded on click; no self-hosted video files |
| 2026-10-04 | Restricted pages and posts show a "Contenu réservé" page (login/register for visitors, required group for logged-in users) instead of redirecting |
| 2026-10-04 | Links and cards to reserved pages stay visible with an access tag (Figma mock-up); a link hidden by its own visibility disappears |
| 2026-10-05 | Back-office with EasyAdmin 5; step 3 split into 3a (reference screens) and 3b (posts and pages); posts and pages get a visual, WordPress-like block editor rather than plain forms |
| 2026-10-05 | Block editor (3b): WordPress-like, built with Stimulus controllers over the existing `Block:*` Twig components (the canvas shows blocks as the site renders them, no second renderer in JS); blocks are added by drag and drop from a block list or with a "/" command and search; text edited in place, other settings in a side panel; mock-up generated in Figma first |
| 2026-10-05 | Resized images with LiipImagineBundle (step 3b) |
| 2026-10-07 | Access model: each post, page, document and file says who may open it (everyone, logged-in users, chosen groups) and what the others get: **announced** (listed with a padlock and the group, "Contenu réservé" at its address) or **private** (listed nowhere, 404 at its address). Administrators see everything. Groups can include other groups (the committee includes the members), rights and access cumulate. Replaces the 2026-10-04 rule "links to reserved pages stay visible" |
| 2026-10-07 | WordPress import: pages move to the tree of the new site (`config/wordpress/import.yaml`, 301 redirects for every moved address), posts to `/actualites/{slug}`; reserved content read with an application password of an administrator; content the anonymous API hides is reserved to the committee |
| 2026-10-06 | News list: reserved posts are listed with their access tag (lock and audience instead of cover and excerpt), like reserved pages; the featured post leads the unfiltered list and is not repeated |
| 2026-10-06 | LiipImagine is only the filter engine: resized WebP versions are stored under `var/uploads/cache/` and served by `MediaController` with the access rules of the original, so restricted pictures stay restricted (the bundle's public cache would bypass them); GD added to the `php` image |
| 2026-10-04 | Comments and "was this article useful?" feedback come later (moderation rules to define); step 2 split into 2a (posts, blocks) and 2b (pages, menus, documents) |

## Open questions

- Member validation: the committee adds the "Membre" group by hand after the Yapla payment; a yearly
  expiry of membership is not modelled yet.
- Article comments and feedback: who can comment, moderation, notifications (after step 4); their
  visual components already exist (`Content:Comment`, `Content:Feedback`).
- Editor canvas width: next to both panels the canvas is narrower than the site's desktop
  breakpoint (64em), so posts show their mobile layout (table of contents above the text); folding
  the panels shows the desktop layout. A desktop-width canvas scaled down is possible if needed.
- PDF block: the browser viewer is used (hidden on mobile, where the open/download links remain); the
  mock-up's page and zoom tools would need PDF.js.
- Title line breaks: the mock-up breaks the article title after "VOTRE PREMIER VOL."; titles wrap
  naturally for now.
