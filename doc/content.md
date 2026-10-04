# Content: posts, blocks and media

How news posts are stored and rendered, and how to add a new kind of block. The visual reference is
the Figma frame "Premier vol · Article desktop" (node 29-4356); the demo post of the fixtures
reproduces it at `/actualites/votre-premier-vol`.

## Model

| Entity | Role |
|---|---|
| `Post` | Title, slug (`/actualites/{slug}`), kicker and level badge, excerpt, optional catchphrase, cover + caption, category, keywords, author, publication date, featured flag, visibility, and three block zones |
| `Category` | Name and slug of a post category |
| `Media` | Uploaded file (image, PDF, text...): stored in `var/uploads`, served by `/media/{id}/{name}` with access control |
| `User` (author fields) | `jobTitle`, `bio`, `avatar` shown in the article header and the author card |

A post is **published** when `publishedAt` is set and in the past. Drafts and scheduled posts are
only visible to users holding `POST_EDIT` (preview banner). `Post` and `Media` implement
`RestrictedContentInterface`: visibility `public`, `authenticated` or `groups` (see
[accounts](accounts.md)).

## Block zones

| Zone | Where it is rendered |
|---|---|
| `body` | Reading column (820px) |
| `aside` | Sidebar, under the table of contents |
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
| `text` | html | Rich text |
| `heading` | text | Subtitle |
| `callout` | variant (`info`, `tip`, `warning`), title, html | |
| `image` | mediaId, caption | Credit comes from the media |
| `image_grid` | images (mediaId, caption) | Pictures side by side |
| `carousel` | title, slides (mediaId, label, caption) | Arrows, thumbnails, keyboard |
| `video` | url, title, subtitle, posterId, caption | YouTube or Vimeo URL; the player loads on click (youtube-nocookie) |
| `quote` | text, attribution | |
| `list` | items (rich text), ordered | |
| `steps` | steps (title, text) | Numbered 01, 02… |
| `tabs` | tabs (label, title, html, facts, linkLabel, linkUrl) | Accessible tabs |
| `checklist` | title, items, note | Ticks kept in the reader's browser |
| `table` | headers, rows, caption | First column = row headers |
| `pdf` | mediaId, title | Browser PDF viewer, open and download links; hidden on mobile |
| `downloads` | files (mediaId, title, description), note | Format and size from the media |
| `notes` | title, rows (label, text), note | |
| `glossary` | title, entries (label = term, text = definition) | |
| `faq` | title, items (question, answer), openFirst | Native `<details>` |
| `links` | links (title, url, description) | |
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
`reading_minutes(post)`.

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

The "Comité" group gets all of them except `POST_DELETE` by default (migration
`Version20261004143458`); the screens using them come with the back-office (roadmap step 3).
