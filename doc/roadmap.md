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

## Next: content and editorial back-office (replaces the WordPress blog and pages)

Do the steps in this order: each one depends on the previous ones.

| # | Step | Content | Status |
|---|---|---|---|
| 1 | Accounts and roles | `User` entity, login, free registration, roles visitor / registered user / member / committee / admin as described in [club.md](project/club.md); a voter that decides who can read a given content | Done (2026-10-04), see [accounts.md](accounts.md) |
| 2 | Content model | `Post` (news: title, slug, excerpt, body, cover image, category, featured flag, visibility, publication date, author), `Category`, `Page` (tree for the 3–4 level menu), `MenuItem` (menu links), `Document` (official texts, PDF files). Each implements `RestrictedContentInterface` (visibility + allowed groups); add the matching permissions (`POST_CREATE`, `POST_PUBLISH`, `PAGE_MANAGE`, `MENU_MANAGE`, `DOCUMENT_MANAGE`...) to `Permission` | To do |
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

## Open questions

- Back-office: EasyAdmin (recommended, not confirmed) or a custom admin.
- Member validation: the committee adds the "Membre" group by hand after the Yapla payment; a yearly
  expiry of membership is not modelled yet.
- Editor for post and page bodies: WYSIWYG (recommended for committee members) or Markdown.
