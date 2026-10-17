# Design system (toolkit)

The visual identity comes from the Figma file **ePlaneur — Grand air adouci · Version 6**
(`a00zZWdxi7zD4Opbt6gIbn`, home page desktop frame `18:53`). It is implemented with plain CSS
(AssetMapper, no build step), [Symfony UX Twig Components](https://symfony.com/bundles/ux-twig-component/current/index.html)
and [Symfony UX Icons](https://symfony.com/bundles/ux-icons/current/index.html).

Living style guide (dev only): **https://eplaneur.local/_toolkit** — every component on every theme.
Add each new component there.

## Principles

- **One value, one place.** Colors, fonts, sizes, spacing and radii are CSS custom properties in
  `assets/styles/tokens.css`. Components never hard-code a value: change a token and the whole site
  follows (e.g. `--color-primary` recolors primary buttons, links, tags, meters...).
- **Components adapt to their surface.** A section sets a theme class (`t-light`, `t-dark`...); the
  theme defines contextual variables (`--surface`, `--on-surface`, `--on-surface-muted`, `--eyebrow`,
  `--link`, `--rule`) that components read. The same `Ui:Link` is navy on cream and white on ink.
- **Mobile first.** Base styles target small screens; layouts expand at `40em` (640px), `48em` (768px)
  and `64em` (1024px). Font sizes are fluid (`clamp()`) between the mobile value and the Figma
  desktop value.
- **Small components, composed.** Pages assemble atoms (`Ui:*`) and patterns (`Card:*`) inside layout
  primitives (`l-*`). Page CSS (`pages/*.css`) only holds what is specific to one page.

## Tokens (`assets/styles/tokens.css`)

| Level | Prefix | Use |
|---|---|---|
| Primitives | `--palette-*`, `--font-*` | Raw Figma values. Only `tokens.css` and `admin.css` (EasyAdmin mapping) read `--palette-*` |
| Semantic | `--color-*`, `--text-*`, `--icon-*`, `--space-*`, `--radius-*`, `--shadow-*`, `--leading-*` | Intent-based aliases used by components |
| Component | `--button-*`, `--tag-*`, `--card-*` | Knobs declared at the top of each component file |
| Surface | `--surface`, `--on-surface`, `--on-surface-muted`, `--eyebrow`, `--link`, `--rule`, `--focus-color` | Set by theme classes |

Neutral colors that do not follow the surface: `--color-text`, `--color-text-muted`,
`--color-on-dark-muted`, `--color-page` (cream), `--color-dark-soft`, `--color-line`,
`--color-line-strong`; inside a themed section prefer `--on-surface` and `--rule`. Icons are sized
with `--icon-xs` (13px) to `--icon-4xl` (30px). Spacing follows the Figma 4px grid (`--space-1` 4px
to `--space-10` 80px), with the half steps the mock-up uses (`--space-1-5` 6px, `--space-2-5` 10px,
`--space-3-5` 14px, `--space-4-5` 18px, `--space-5-5` 22px). A value used by one component only
stays in it, with its Figma size in a comment.

Keyboard focus is a 2px outline in `--focus-color` (`base.css`): navy on light surfaces, sand on
`t-dark` and `t-primary`, so it keeps a 3:1 contrast and stays visible in forced-colors mode.

Palette (Figma): ink `#192630`, ink-soft `#273642`, navy `#3e5d83` (primary), sand `#e7bca7`
(accent), sand-soft `#f1ded2`, sky-soft `#e5ebf1`, cream `#f7f5ef`, slate `#586772` (muted text),
mist `#ced8df`, line `#d8ddd9`.

Fonts, served by the site (`assets/fonts/`, `@font-face` in `styles/fonts.css`, Latin and Latin
Extended subsets, main files preloaded by `_fonts.html.twig`): **Oswald** 500/600 for display titles
and big numbers, **Barlow** 700/800 for headings, **Inter** 400–800 for text and UI. No request to
Google Fonts, so no consent is needed for them. `--font-mono` is the system monospace font, for text
meant to be copied as is (the instructions of the flight plan workshop).

Line heights follow Figma: where a Figma text layer uses the "Auto" line height, the CSS uses
`line-height: normal` (the font's own metrics, about 1.48 for Oswald and 1.2 for Barlow), not a fixed
ratio. Strokes are drawn inside in Figma: outlined cards use an inset `outline` so the border does not
add to their size. Heading sizes beyond the four levels (`--text-h2-lg` 32px, `--text-h3-sm` 28px,
`--text-h3-xs` 27px) cover the few Figma titles in between.

### Surface themes

| Class | Background | Text | Eyebrow / links |
|---|---|---|---|
| `t-light` (default) | cream | ink | navy |
| `t-paper` | white | ink | navy |
| `t-dark` | ink | white / mist | sand / white |
| `t-primary` | navy | white | sand / white |
| `t-accent` | sand | ink | navy |
| `t-warm` | sand-soft | ink | navy |

## Files

```
assets/styles/
  app.css             entry point, imports everything in order
  fonts.css           @font-face of the self-hosted fonts (assets/fonts/)
  tokens.css          tokens + surface themes, breakpoints in its header
  base.css            reset and element defaults
  layout.css          l-* layout primitives, u-* utilities
  components/         c-* component styles, one file per component (typography, button, card, toc,
                      post-card, breadcrumb...)
  blocks/             one file per content block type (text.css, callout.css, faq.css...) and
                      flow.css, the gaps between blocks
  pages/              p-* page compositions (home.css, account.css, article.css, page.css, news.css)
assets/icons/         SVG icons (currentColor), used with <twig:ux:icon name="..."/>
assets/images/        pictures (home/ = Figma mock-up visuals)
templates/components/ Twig components: Ui/ (atoms), Card/ (patterns), Layout/, Site/ (header, footer)
```

Naming: `l-` layout, `c-` component (BEM: `c-card__body`, `c-button--accent`), `p-` page-specific,
`t-` theme, `u-` utility.

Breakpoints, mobile first and always in the range syntax (custom properties cannot be used in media
queries): `(width >= 40em)` two columns of cards, `(width >= 48em)` tablets, `(width >= 64em)` desktop
(mega-menu, article sidebars); `(width < 75em)` only in the back-office block editor. The JS uses the
same queries (`matchMedia`).

## Layout primitives

| Class | Behaviour |
|---|---|
| `l-container` | Centered content, max 1296px, side padding `--gutter` (20 → 72px) |
| `l-section` | Vertical section padding (56 → 80px) |
| `l-stack` (`--xs/--sm/--md/--lg`) | Vertical flow with gap |
| `l-cluster` (`--tight/--spread`) | Wrapping inline group |
| `l-grid` (`--2/--3/--4`, `--tight/--loose`) | 1 column on mobile, n columns on larger screens |
| `l-rail` | Horizontal swipe list with scroll snap on mobile, 3-column grid from 64em (cards collections) |
| `l-split` (`--aside-md/--aside-lg`) | Two columns from 64em, stacked below |

## Components

All components are anonymous Twig components: props are declared at the top of each file with a
usage example.

| Component | Purpose | Main props |
|---|---|---|
| `Ui:Button` | Call to action (`<a>` or `<button>`) | `href`, `variant` (primary, dark, accent, outline, danger), `icon`, `block`, `size` (md, sm) |
| `Ui:Link` | Text link with arrow | `href`, `icon` |
| `Ui:Eyebrow` | Uppercase label above titles | — |
| `Ui:Heading` | Heading with independent tag and size | `level`, `size` (h1–h4) |
| `Ui:Tag` | Label or filter toggle | `variant` (default, muted, soft), `pressed` |
| `Ui:Badge` | Label over a picture | `size` |
| `Ui:Logo` | Glider mark + name | `href` |
| `Ui:Date` | Big day + meta lines | `day`, `meta` |
| `Ui:Stat` | Key figure tile | `label`, `value`, `unit` |
| `Ui:Meter` | Labelled progress bar | `label`, `value`, `percent`, `highlight` |
| `Ui:ChapterList` | Numbered list of links | `items` |
| `Ui:Dropzone` | File drop area (real file input) | `name`, `title`, `hint`, `accept` |
| `Ui:Figure` | Rounded picture with badge and caption | `src`, `alt`, `badge`, `caption` |
| `Ui:Profile` | Icon + title + text line | `icon`, `title`, `text` |
| `Ui:Breadcrumb` | Breadcrumb trail, last item = current page | `items` ({label, href}) |
| `Ui:Access` | Access tag of reserved content: lock + audience (menus, cards, documents) | content = group names or "Connectés" |
| `Layout:Section` | Themed full-width section with container | `theme`, `tag`, `container` |
| `Layout:SectionHeader` | Eyebrow + title + lead + optional `aside` block | `eyebrow`, `title`, `lead`, `level` |
| `Card:Feature` | Tinted card with icon tile | `icon`, `title`, `text`, `tone` (warm, cool) |
| `Card:Session` | Network flight session | `day`, `month`, `year`, `version`, `schedule`, `title`, `level`, `text`, `href` |
| `Card:Guide` | Guide with chapters and CTA | `eyebrow`, `title`, `text`, `chapters`, `ctaLabel`, `ctaHref` |
| `Card:News` | Editorial card with an optional picture (the featured post is `Card:PostFeatured`) | `eyebrow`, `title`, `text`, `image`, `imageAlt`, `href`, `linkLabel` |
| `Card:Step` | Numbered step of a path | `number`, `title`, `text`, `linkLabel`, `href` |
| `Card:Post` | Article card: picture, category, title, date, excerpt, reading time; a reserved post the reader may not open shows a lock and its audience | `post`, `readingMinutes`, `dated` |
| `Card:PostFeatured` | Featured post ("À la une"): cover or year poster next to (`split`) or above (`stacked`) a navy panel | `post`, `readingMinutes`, `layout`, `level` |
| `Content:Blocks` | Renders a list of content blocks with their `Block:*` components (see [content](content.md)) | `blocks` |
| `Content:Toc` | Article table of contents with reading progress (`toc` controller) | `entries` |
| `Content:Share` | Copy link, e-mail, native share (`share` controller) | `url`, `title` |
| `Content:AuthorCard` | Author presentation | `author` |
| `Content:PageHeader` | Institutional page header: breadcrumb, kicker, Oswald title, lead, highlight, "Mis à jour" line | `crumbs`, `kicker`, `title`, `lead`, `highlight`, `highlightNote`, `updatedAt` |
| `Content:Comment`, `Content:Feedback` | Comment and "was it useful?" (visual only, not wired yet) | see files |
| `Site:Header` | Header from the `main` menu. From 64em: sticky row, a section opens its mega-menu (columns of second-level links, third-level links with note and access tag, "Toute la rubrique" and current page). Below: "Menu" button, full-width member button, full-screen panel with accordions. `menu` Stimulus controller | — |
| `Site:Footer` | Footer with link columns and legal line | — |

Other CSS-only components: `c-display`, `c-tagline`, `c-motto`, `c-callout`, `c-link-bar`,
`c-icon-tile`, `c-definition-list`, `c-level-badge`, `c-keyword`, `c-avatar`, `c-tag--outline` (unselected
filter), `c-pagination` (pages of a list).

Content blocks (`Block:*` components, one per block type, styles in `blocks/<type>.css`) are
described in [content](content.md). Article typography tokens: `--text-article-title`,
`--text-article-lead`, `--text-section-title`, `--text-band-title`, `--text-quote`,
`--text-title-md/sm/xs`, `--leading-reading`; layout tokens `--article-sidebar`, `--article-gap`,
`--article-column`. Navigation and page tokens: `--text-menu-column`, `--text-menu-row`,
`--text-menu-row-open`, `--text-restricted-title`. Institutional pages (`pages/page.css`): a root
page spreads its blocks over the full width, a sub-page has the section's pages on the left
(`c-subnav`); the "Contenu réservé" card is `c-restricted`.

Forms: Symfony forms are rendered by the site form theme `templates/form/theme.html.twig`
(registered in `config/packages/twig.yaml`), which outputs `c-field` rows (`c-field__label`, `c-input`,
`c-field__help`, `c-field__errors`, `c-field--invalid`); wrap fields and the submit button in a
`c-form`. `c-check` styles a checkbox with its label, `c-alert` (`--success`, `--error`) the flash
messages and form-level errors. Feedback colors (`--color-danger`, `--color-success` and their `-soft`
backgrounds) are not in the Figma file yet.

Example:

```twig
<twig:Layout:Section theme="dark" id="vols">
    <twig:Layout:SectionHeader eyebrow="02 / Les prochains vols" title="On se retrouve là-haut.">
        <twig:block name="aside"><twig:Ui:Link href="#">Tous les vols</twig:Ui:Link></twig:block>
    </twig:Layout:SectionHeader>
    <div class="l-rail">
        <twig:Card:Session day="08" month="Oct." year="2026" version="Condor 3" ... />
    </div>
</twig:Layout:Section>
```

## Icons

SVG files in `assets/icons/`, exported from Figma and normalised to `currentColor` (they take the
text color). Render them with `<twig:ux:icon name="arrow-right" />` (size `1em` by default, set
`font-size` or `width/height` to resize). Available: `arrow-right`, `chevron-right`, `glider`,
`settings`, `upload`, `discover`, `progress`, `share`, and for articles `arrow-up-right`, `check`,
`chevron-left`, `clock`, `download`, `file-text`, `info`, `lightbulb`, `link`, `mail`,
`message-circle`, `minus`, `play`, `plus`, `printer`, `share-2`, `thumbs-up`, `triangle-alert`, and
for navigation `chevron-down`, `chevron-up`, `lock-keyhole`, `menu`, `x`. `assets/icons/admin/` holds
the back-office icons (`admin:` prefix, see [admin](admin.md#theme)).

## Adding or changing something

1. A color, font or size used in several places → token in `tokens.css`.
2. A reusable UI piece → Twig component in `templates/components/` + styles in
   `assets/styles/components/` + an entry in `/_toolkit` and in the table above.
3. A page-specific arrangement → `assets/styles/pages/<page>.css` with `p-` classes.
4. Check at 390px and 1440px wide (the Figma desktop width).

## Home page status

`templates/home/index.html.twig` implements the Figma home page. Sections live in
`templates/home/sections/`. Real data: the next session and the coming or latest sessions, the
posts, and the logbook section (the showcase flight replayed in 3D with its figures and the head of
its session results, the upload form for logged-in readers, the latest public flights). Every link
leads to a route or to a page of the tree (`path('app_page_show', {path: ...})`, some with the anchor
of a section of the page); a test checks that no link of the page points to `#`.

Still placeholder (2026-10-16):

- Images: the hero, the cockpit and the community pictures are illustrations ("Image d’ambiance ·
  Pas une capture de Condor").
- Guides: the club has no guide per version of Condor. Both cards of "La technique, en clair" link
  their chapters to the shared pages (installation, prerequisites per version, connection to the
  server) and their button to "Bien démarrer"; the joystick and display settings of the mock-up have
  no page yet.
- Texts: the slogans and the three profiles ("Je découvre", "Je progresse", "Je partage") are the copy
  of the mock-up, to review by the committee.
- Page anchors (`#voler-avec-condor-2`, `#connexion-au-serveur-condor`) come from section titles: if an
  editor renames a section, the link opens the top of the page.

Hero background video: on large screens (64em and more) the hero plays `assets/videos/home/hero.mp4`
over its image, muted and in a loop (`hero_video` controller). It is the video of the home page of the
current site (`planeurs-condor3.mp4`, Condor 3 footage) without its Condor 3 logos and the red name of
each glider: only the shots 17.25–23.25 s, 28.5–40.25 s, 46.75–60.25 s, 86–104 s and 114.5–133 s are
kept (1 min 08), re-encoded without sound at 24 fps (CRF 32, `+faststart`), 5.6 MB instead of 33. The
file is requested only on large screens, never when the reader prefers reduced motion or saves data;
it fades in once it plays, the legend then reads "Vidéo · Planeurs en vol sur Condor 3", and a round
button pauses it.
