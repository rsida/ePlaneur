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

## Next: content and editorial back-office (replaces the WordPress blog and pages)

Do the steps in this order: each one depends on the previous ones.

| # | Step | Content | Status |
|---|---|---|---|
| 1 | Accounts and roles | `User` entity, login, free registration, roles visitor / registered user / member / committee / admin as described in [club.md](project/club.md); a voter that decides who can read a given content | Done (2026-10-04), see [accounts.md](accounts.md) |
| 2a | Posts and content blocks | `Post`, `Category`, `Media` (uploads served with access control); article body = ordered list of typed blocks (JSON) covering every block of the Figma article (node 29-4356): text, heading, callout, image, image pair, carousel, video, quote, list, steps, tabs, checklist, table, PDF reader, downloads, notes, glossary, FAQ, links, sidebar resource, takeaways; article page (`/actualites/{slug}`) with table of contents, reading progress, sharing, author, related posts; demo article in fixtures; post permissions | Done (2026-10-04), see [content.md](content.md) |
| 2b | Pages, menus, documents | `Page` (tree for the 3–4 level menu), `MenuItem` (menu links filtered with `CONTENT_VIEW`), `Document` (official texts); each implements `RestrictedContentInterface`; matching permissions (`PAGE_MANAGE`, `MENU_MANAGE`, `DOCUMENT_MANAGE`) | To do |
| 3 | Admin with EasyAdmin | CRUD for posts, categories, pages, menu, documents, users and groups (with their permissions), each screen and action restricted by permission; rich text editor for non-technical editors | To do |
| 4 | Public front | News list, post page, filter by category and date; the home page "Ça bouge au club" section reads featured posts instead of placeholders; pages rendered from the tree with the menu | To do |
| 5 | WordPress migration | `app:import-wordpress` command reading posts, pages and media through the WordPress REST API (79 pages, 34 posts, about 20 of them restricted committee minutes); 301 redirects from the old URLs | To do |

Why this order: restricted content (committee minutes, 4th-level pages) and the back-office both need
accounts and roles first; EasyAdmin gives a usable back-office quickly, a custom admin is only worth it
later for business screens (network flights, progression tracking); the import avoids retyping the
existing content.

## Decisions

| Date | Decision |
|---|---|
| 2026-10-04 | Rights = permissions (fixed catalogue in code) held by groups stored in database; a user belongs to several groups; administrators edit groups and their permissions. Content visibility = public, logged-in users, or chosen groups |
| 2026-10-04 | Free registration requires e-mail confirmation; password reset by e-mail |
| 2026-10-04 | Content bodies are an ordered list of typed blocks (CMS-like), stored as JSON; rich text inside blocks is limited HTML (bold, italic, links), sanitized |
| 2026-10-04 | Videos are YouTube (youtube-nocookie) or Vimeo URLs, loaded on click; no self-hosted video files |
| 2026-10-04 | Comments and "was this article useful?" feedback come later (moderation rules to define); step 2 split into 2a (posts, blocks) and 2b (pages, menus, documents) |

## Open questions

- Back-office: EasyAdmin (recommended, not confirmed) or a custom admin.
- Member validation: the committee adds the "Membre" group by hand after the Yapla payment; a yearly
  expiry of membership is not modelled yet.
- Article comments and feedback: who can comment, moderation, notifications (after step 4); their
  visual components already exist (`Content:Comment`, `Content:Feedback`).
- Image sizes: pictures are served as uploaded; generate resized versions (LiipImagine or similar)
  when the editor allows uploads (step 3).
- PDF block: the browser viewer is used (hidden on mobile, where the open/download links remain); the
  mock-up's page and zoom tools would need PDF.js.
- Title line breaks: the mock-up breaks the article title after "VOTRE PREMIER VOL."; titles wrap
  naturally for now.
