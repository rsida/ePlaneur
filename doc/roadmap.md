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
| Step 5: WordPress import, access model | [wordpress-import.md](wordpress-import.md): `app:import-wordpress` (pages in the new tree, posts, files, 301 redirects); [accounts.md](accounts.md#access-to-content): announced or private content, group inclusion |

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
| 5 | WordPress migration | `app:import-wordpress` command reading posts, pages and media through the WordPress REST API (79 pages, 34 posts, about 20 of them restricted committee minutes); 301 redirects from the old URLs | Done (2026-10-07), see [wordpress-import](wordpress-import.md); reserved content read with an application password |

## Next: audit follow-ups (step 6, chosen on 2026-10-07)

| # | Batch | Status |
|---|---|---|
| 6a | Editor UX (see the batch below) | Done |
| 6b | Code | Done |
| 6c | CSS | Done |

## Next: proposals for the committee (steps 7 and 8, proposed on 2026-10-08)

Replacing the WordPress site is not decided yet: the committee first has to be convinced. A briefing
for the committee (what the site brings, a 15-minute demo scenario, the decisions to take, the
questions for the treasurer) was written on 2026-10-09 as a private page:
https://claude.ai/artifact/4EZDZFXH8wzvbM33wmnELQ (share it from its page). These two
steps show what a custom site brings that WordPress cannot; step 8 needs the committee's and the
treasurer's agreement before any code.

| # | Step | Content | Status |
|---|---|---|---|
| 7 | Flight viewer | Condor Club-like flight analysis focused on training: upload a Condor flight track, see it on a map from above and in 3D at its real altitude over the relief, replay it, read the computed values (ground speed, altitude, vario, thermals, glides, task legs), then replay a whole network session with every pilot, compare flights and follow each pilot's progression | Done (2026-10-09); polars of the gliders, netto and 3D gliders added on 2026-10-10. Not yet: the pilot view of 7e (to rework) |
| 7a | One flight | Read Condor 3 `.ftr` files and IGC files; exact coordinates from the landscape projection; analysis (1 s series, speed, vario, circling and glide phases, statistics); flight page with 2D map, 3D view, charts and replay; upload by logged-in users; launch (airborne, aerotow, winch) read from the track | Done (2026-10-08), see [flights](flights.md); to try with the committee |
| 7b | Network session | Flights of the same flight plan grouped automatically on a session page: ranking by task speed, replay of all pilots together on the map and in 3D, session podium beyond speed (best climber, best glide ratio, least time circling) | Done (2026-10-08), sessions grouped by flight plan (see [flights](flights.md)); to try with the tracks of several pilots of one race |
| 7c | Comparison | Compare a flight with another of the same race (the winner by default): time gained or lost leg by leg and along the track, thermals taken by each | Done (2026-10-08), see [flights](flights.md); IGC files now join sessions through their task name |
| 7d | Progression | Rule-based advice tied to the training path (time spent circling, weak thermals accepted, always turning the same way, speed to fly); pilot logbook (average speed, climb rate and glide ratio over the weeks); instructor comments at a moment of a flight, linked to a step of the training path | Done (2026-10-08), see [flights](flights.md#progression); trainers: a group with `FLIGHT_REVIEW` |
| 7e | Viewer extras | Cockpit camera in 3D replay; link to a moment of a flight (debriefings); two thermals or legs side by side; public flights chosen by the pilot (showcase on the home page) | Done (2026-10-08), see [flights](flights.md#viewer); legs are compared between flights (7c), not within one; the pilot view is to be taken up again (smoothness and loading of the relief in thermals, to judge on a real machine) |
| 7f | Club life | Club records per task or landscape, badges (first 300 km, first 5 h flight), season ranking; kept modest so that the site stays a training tool, not a game | Done (2026-10-08), see [flights](flights.md#club-life); a season is a calendar year for now (the club season may start in September) |
| 7g | Condor Club parity (asked on 2026-10-09) | At least what the Condor Club flight page offers (screenshot of 2026-10-09), split below | Done; polars added on 2026-10-10 (7g2); 3D replay brought close to Condor Club's from a screenshot of 2026-10-10 (readings in the flag, task cylinders, thermal columns, turnpoints on the timeline); not their clouds (the weather of the task is not known) |
| 7g1 | Figures from the track | Max speed, flight time, best altitude gain, cumulative climb, landing during the flight; turns right / left with their average climb; turnpoint table (time, altitude, leg duration, total, speed when turning); final glide (last climb, distance, arrival height, excess height and the time it cost); detour of the transitions against straight lines; band used (entry and top heights, AGL); map coloured by altitude (or vario) with its legend | Done (2026-10-09), see [flights](flights.md#analysis): "Le vol en détail", "Transition finale", "Points de virage" on the flight page, track coloured by vario or altitude; checked against the Condor Club page of C3-s117 (spirals are counted differently) |
| 7g2 | Phase analysis and coaching | Phase table with turns, radius, bank, drift (wind) per thermal and L/D, netto per transition; spiral gauge (radius against the best quarter of the day); transition speed by MacCready band; wind by altitude (spiral drift); climb rate by altitude; slope or wave lift as its own phase; effective MacCready and ideal speed; netto and the speeds to fly need the polar of each glider (to collect) | Done without the polars (2026-10-09), see [flights](flights.md#analysis): phase table, spiral, climb and wind by altitude, slope or wave lift, advice (wandering transitions, wide spiral, high finish). Polars and sheets of the gliders read from the Condor `Planes` folder (2026-10-10, decision: gather as much as possible): glider pages, wing loading of the flight, polar with the glides of the flight, MacCready speed against the glide speed in the advice; netto of each glide and second by second, effective MacCready of each glide (2026-10-10) |
| 7g3 | Race view | Rank of the flight among the tracks of its race (climb, transition); thermal map of the race built from every track, with a time slider; compare to the winner or to any pilot in one click (7b exists); download the original file and an IGC export | Done (2026-10-09), see [sessions](sessions.md#race-view) and [flights](flights.md#downloads): rank among the tracks of the race, thermal map of the race with a time slider from the start line, compare to the winner, original file and IGC export |
| 7g4 | Task and settings from the .ftr header | Condor version, registration, country; task date, time, start delay and start time, multiplayer, ghosts; water ballast, wing loading and the optimum, fixed mass, centre of gravity; penalty and realism settings; weather (preset, clouds, thermals, season, time of day, wind) | Done another way (2026-10-09): the settings come from the Condor Club page of the task or its `.fpl`, given when the session is prepared (see [sessions](sessions.md#task-settings)); water ballast read from the `.ftr`. The settings in the `.ftr` header itself look encrypted and stay unread; the clock time of the records is not known either |
| 8 | Membership and finances (replaces Yapla) | Membership campaigns per season with online payment through **HelloAsso Checkout** (no card handled by the site), automatic "Membre" group with an expiry date, membership certificate, renewal reminders; then the treasurer's books (income and expenses, receipts, budget vs actual, exports for the general meeting); import of the Yapla history | Split below |
| 8a | Memberships | Seasons and fees, requests on `/adhesion` with the data of the membership form, payment recorded by the treasurer, validation into the "Membre" group, certificate, end of membership after a grace month, renewal reminders, CSV export | Done (2026-10-08, started before the treasurer's input at the user's request), see [membership](membership.md) |
| 8b | Online payment | HelloAsso Checkout: the request redirects to the payment page, the payment notification marks the membership paid | Done (2026-10-08), see [membership](membership.md#online-payment); off until the club's HelloAsso API keys are set (try the sandbox first) |
| 8c | Treasurer's books | Income and expenses, receipts, budget against actual, general meeting exports, import of the Yapla history | Waiting for the treasurer's needs |
| 9 | Members and contact (asked on 2026-10-09) | Member space, contact form, member search, comparing a whole session, flight plan pages | Done (2026-10-10) |
| 9a | Member space | `/mon-compte` becomes a personal dashboard: membership, latest flights, progression, trainers' comments, profile (Condor CN and pilot name to link tracks, notification preferences), personal data (GDPR): download my data, delete my account (comments and messages deleted; flights deleted or kept anonymised, the member's choice; memberships and payments kept for the books with the name only) | Done (desktop Figma, node 85-2; to re-check against the mobile frames once finished), see [member space](member-space.md). Messages (9c), the training path (9g) and the next session card (9f) added since |
| 9b | Contact form | Public form (subject, message; name and e-mail filled in for a logged-in account, anti-spam), requests listed in the back-office with a "processed" state, e-mail to the club for each new request | Done: `/contact`, back-office "Messages de contact" (`CONTACT_MANAGE`, committee), e-mail to `CONTACT_EMAIL` |
| 9c | Messages | Conversations between members and trainers or the committee, possibly about a moment of a flight; e-mail notification | Done (2026-10-10), see [member space](member-space.md#messages): members write to each other, to the committee or to the administrators (decision of 2026-10-10), possibly about a flight; e-mail to the readers; no moderation yet |
| 9d | Member search | Back-office: search members (name, e-mail, CN, group, membership state) and see their flights, logbook and memberships from their record | Done (2026-10-09): "Membres" in the back-office (`MEMBERSHIP_MANAGE`, committee), see [back-office](admin.md) |
| 9e | Whole-session comparison | Compare every flight of a session together (not two by two): gaps along the task of each pilot against the best, charts of all pilots, key moments per pilot | Done (2026-10-09): "Comparer toute la session", see [sessions](sessions.md#race-view) |
| 9f | Sessions and results | Network sessions as records of the club site: date, flight plan, dedicated or open, organiser, results of the dedicated server, tracks of the race; flight plan typed with suggestions and recorded with the address of its sheet on eplaneur.fr; a page per session and per flight plan; history of eplaneur.fr | Done (2026-10-09), see [sessions](sessions.md): `/sessions`, `/plans-de-vol/{code}`, members propose sessions (`SESSION_ORGANISE`), committee manages them (`SESSION_MANAGE`); tracks join their session by race id; `app:sessions:import` brought the 635 sessions of eplaneur.fr since 2020 from its public pages (organisers unknown); the export asked to the owner of eplaneur.fr is no longer needed for the history |
| 9g | Training path follow-up | The 8 steps of the training path in each pilot's logbook: a trainer marks a step in progress or validated (date, note, reference flight), the pilot sees where they stand; replaces the Excel workbooks on Google Drive (see [training](project/training.md#progression-tracking)) | Done as a proposal (2026-10-09), see [flights](flights.md#training-path): to show the committee, which has not validated the follow-up yet |

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
| Editor UX (done 2026-10-07) | Warn that saving a published post updates the site at once; open the tab and highlight the field of a validation error; choose the visibility of files uploaded from the editor; state filter and "À la une" column in the post list; keyboard and ARIA for the block menus and format buttons; focus management of the tablet panels and of the site's mobile menu; draft backup in the browser |
| Code (done 2026-10-07) | One base controller and shared form fields for the post and page editors; one access-label service (done 2026-10-07); N+1 queries on the groups of reserved content; author field only with POST_EDIT; unpublished sections hide their sub-pages; editor preview banner only for unpublished content; slug length and page number validation; `ClockInterface` instead of hard-coded `Europe/Paris`; tests for the category and menu screens |
| CSS (done 2026-10-08) | Split `content.css` into one file per component; semantic tokens instead of `--palette-*` and literal sizes; one set of breakpoints; merge `c-news--featured` into `c-featured-post`; self-hosted fonts instead of Google Fonts |

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
| 2026-10-08 | Flight viewer: reads Condor 3 `.ftr` files (reverse-engineered: fixed header, 64-byte records; positions converted exactly with the UTM projection stored in each landscape's `.trn` header) and IGC files; 2D and 3D with MapLibre GL, deck.gl for the track at its real altitude and AWS Terrain Tiles for the relief (no API key) |
| 2026-10-08 | Times of the `.ftr` records decoded (float of 32-hour units, bits offset by 0x500000), checked against the IGC export of the same flight: durations and speeds are exact; Condor's 180° start and finish zones are half discs |
| 2026-10-09 | Deleting an account (9a): the member chooses whether their flights are deleted too or kept anonymised (pilot name, competition id and account removed), so that sessions and records stay complete; today they are kept with the pilot name of the file |
| 2026-10-09 | Roles of the two sites: eplaneur.fr is the library (equipment, documentation, list of flight plans and their technical sheets: `/rechercher-fpl/`, `/afficher-fpl/` stay there); club.eplaneur.fr runs the life of the club, so network sessions and their results move to it in time (`/afficher-sessions/`, `/afficher-tableau-resultats-session/` today on eplaneur.fr), each session linking to the sheet of its flight plan on eplaneur.fr |
| 2026-10-09 | Sessions (9f): tracks join their session by the race id of the `.ftr` file, the same as in the name of the results file of the Condor dedicated server; results come from that file (uploaded by the organiser, or read from eplaneur.fr); members propose sessions and become their organiser, the committee manages all of them; session and flight plan pages are public, results and tracks need an account |
| 2026-10-10 | Gliders: take as much as possible from the Condor installation (`Condor3/Planes`: sheet and polar of each glider), imported by a command into the database, not committed |
| 2026-10-10 | 3D replay: gliders drawn turned and banked with a flag (name, altitude), fading trails and a curtain under the pilot followed, camera behind the glider, satellite in 3D, after SportsTrackLive and Condor Club |
| 2026-10-10 | Messages (9c): members write to each other, to the committee or to the administrators |
| 2026-10-04 | Comments and "was this article useful?" feedback come later (moderation rules to define); step 2 split into 2a (posts, blocks) and 2b (pages, menus, documents) |

## Open questions

- Flight tracks: the task time of C3-c060 is 63 s shorter than the Condor result (4,846 s against
  1 h 21 min 49 s on the server): its `.fpl` gives a regatta start (window 0), so the official time
  counts from the opening of the gate; the flight page shows the official time of the results. And a flight with a crash in the middle
  (C3-c058: 500 m higher, then a fast fall) keeps only one of its parts: how should it be shown?
- Sessions (9f): results and pilot names are public on eplaneur.fr; on the club site they need an
  account. Should they be public? Should the session menu items (Afficher sessions, Résultats session)
  point to `/sessions` now?
- GDPR (9a): what does the club keep, and for how long (privacy policy to write)?
- Messages (9c): should a member be able to report a message, and who would read the reports? Should
  trainers (`FLIGHT_REVIEW`) be offered as a group to write to?
- Gliders: the polars of Condor do not say at which mass they are given; taken without water at the
  minimum wing loading. To check against a Condor Club flight analysis of a ballasted glider.
- Season ranking: a season is a calendar year. Does the club count its seasons from September, like
  the membership? Should the ranking stay at all once the committee has seen it?
- Flight lists hydrate the whole analysis (about 250 KB of JSON per flight): fine for a few dozen
  flights; with hundreds, move the analysis to its own table loaded only by the viewer.

- Memberships (8a): what does the membership form of the internal rules ask exactly (fields sent to
  the FFVP, image rights, emergency contact)? Is the club year the calendar year (assumed), and is a
  grace month right? The age limit of the reduced rate is checked on 1 January of the season.
- Article comments and feedback: who can comment, moderation, notifications (after step 4); their
  visual components already exist (`Content:Comment`, `Content:Feedback`).
- Editor canvas width: next to both panels the canvas is narrower than the site's desktop
  breakpoint (64em), so posts show their mobile layout (table of contents above the text); folding
  the panels shows the desktop layout. A desktop-width canvas scaled down is possible if needed.
- PDF block: the browser viewer is used (hidden on mobile, where the open/download links remain); the
  mock-up's page and zoom tools would need PDF.js.
- Title line breaks: the mock-up breaks the article title after "VOTRE PREMIER VOL."; titles wrap
  naturally for now.
