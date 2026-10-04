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
| Primitives | `--palette-*`, `--font-*` | Raw Figma values. Never used directly by components |
| Semantic | `--color-*`, `--text-*`, `--space-*`, `--radius-*`, `--shadow-*`, `--leading-*` | Intent-based aliases used by components |
| Component | `--button-*`, `--tag-*`, `--card-*` | Knobs declared at the top of each component file |
| Surface | `--surface`, `--on-surface`, `--on-surface-muted`, `--eyebrow`, `--link`, `--rule` | Set by theme classes |

Palette (Figma): ink `#192630`, ink-soft `#273642`, navy `#3e5d83` (primary), sand `#e7bca7`
(accent), sand-soft `#f1ded2`, sky-soft `#e5ebf1`, cream `#f7f5ef`, slate `#586772` (muted text),
mist `#ced8df`, line `#d8ddd9`.

Fonts (Google Fonts, loaded in `base.html.twig`): **Oswald** 500/600 for display titles and big
numbers, **Barlow** 700/800 for headings, **Inter** 400–800 for text and UI.

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

## Files

```
assets/styles/
  app.css             entry point, imports everything in order
  tokens.css          tokens + surface themes
  base.css            reset and element defaults
  layout.css          l-* layout primitives, u-* utilities
  components/         c-* component styles (typography, button, tag, card, data, media, form, site)
  pages/              p-* page compositions (home.css, account.css)
assets/icons/         SVG icons (currentColor), used with <twig:ux:icon name="..."/>
assets/images/        pictures (home/ = Figma mock-up visuals)
templates/components/ Twig components: Ui/ (atoms), Card/ (patterns), Layout/, Site/ (header, footer)
```

Naming: `l-` layout, `c-` component (BEM: `c-card__body`, `c-button--accent`), `p-` page-specific,
`t-` theme, `u-` utility.

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
| `Ui:Button` | Call to action (`<a>` or `<button>`) | `href`, `variant` (primary, dark, accent, outline), `icon`, `block` |
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
| `Layout:Section` | Themed full-width section with container | `theme`, `tag`, `container` |
| `Layout:SectionHeader` | Eyebrow + title + lead + optional `aside` block | `eyebrow`, `title`, `lead`, `level` |
| `Card:Feature` | Tinted card with icon tile | `icon`, `title`, `text`, `tone` (warm, cool) |
| `Card:Session` | Network flight session | `day`, `month`, `year`, `version`, `schedule`, `title`, `level`, `text`, `href` |
| `Card:Guide` | Guide with chapters and CTA | `eyebrow`, `title`, `text`, `chapters`, `ctaLabel`, `ctaHref` |
| `Card:News` | Editorial card, image or featured poster | `eyebrow`, `title`, `text`, `image`, `featured`, `poster`, `posterLabel`, `href`, `linkLabel` |
| `Card:Step` | Numbered step of a path | `number`, `title`, `text`, `linkLabel`, `href` |
| `Site:Header` | Sticky header, burger menu below 64em (`menu` Stimulus controller) | — |
| `Site:Footer` | Footer with link columns and legal line | — |

Other CSS-only components: `c-display`, `c-tagline`, `c-motto`, `c-callout`, `c-link-bar`,
`c-icon-tile`, `c-definition-list`.

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
`settings`, `upload`, `discover`, `progress`, `share`.

## Adding or changing something

1. A color, font or size used in several places → token in `tokens.css`.
2. A reusable UI piece → Twig component in `templates/components/` + styles in
   `assets/styles/components/` + an entry in `/_toolkit` and in the table above.
3. A page-specific arrangement → `assets/styles/pages/<page>.css` with `p-` classes.
4. Check at 390px and 1440px wide (the Figma desktop width).

## Home page status

`templates/home/index.html.twig` implements the Figma home page with **placeholder content**
(sessions, news, logbook figures are hard-coded examples). Sections live in
`templates/home/sections/`. The hero background video is planned: replace the `<img>` in
`_hero.html.twig` with a `<video>` when available. Links point to `#` until the target pages exist.
