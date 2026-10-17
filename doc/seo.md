# Search engines and AI answer engines (SEO and GEO)

What the site tells search engines (Google, Bing) and AI answer engines (ChatGPT, Perplexity,
Copilot, Google AI Overviews), and what editors can set. Built on 2026-10-13 (roadmap step 10).

## Principles

- **Only the public showcase is indexed**: home, pages, news, membership, contact, "Mon premier vol" and the gliders of
  Condor. Flights, logbooks, sessions, flight plans, account and back-office pages carry
  `noindex`: they hold the names of pilots (decision of 2026-10-13).
- **Every crawler is welcome**, AI ones included (training and search), for the widest reach
  (decision of 2026-10-13). Change `templates/seo/robots.txt.twig` to close some of them.
- **Nothing is required from editors**: titles, descriptions and pictures come from the content.
  The "Référencement" settings of the editor only replace them when needed. The pages WordPress
  gives no lead get one from `config/wordpress/import.yaml` (`leads`, see
  [WordPress import](wordpress-import.md)).
- **GEO is content, not fields**: answer engines quote pages that answer a question clearly. The FAQ
  and glossary blocks are described to them (below); a lead (chapô) on every page helps the most.

## What every page carries

`templates/base.html.twig`, with `App\Seo\SearchEngines` (`seo_indexable()`, `seo_canonical()`):

| Tag | Value |
|---|---|
| `<title>` | Block `title` followed by "· Club ePlaneur" |
| `meta description` | Block `description` |
| `meta robots` | `index, follow, max-image-preview:large` on the routes of `SearchEngines::INDEXABLE`, `noindex, follow` elsewhere (block `robots`) |
| `link rel=canonical`, `og:url` | Absolute address of the route, without query parameters except `categorie` and `page` (not `page=1`) |
| Open Graph, Twitter card | `og:title`, `og:description`, `og:type` (block `og_type`), `og:image` (block `og_image`, the home picture by default), `summary_large_image` |
| JSON-LD | Block `structured_data` |

Pages and posts are indexed when published, public (pages: also every page above them) and not
marked "Ne pas référencer"; a preview never is.

## Search settings of posts and pages

The "Référencement" panel of the block editor (`App\Content\SearchSettings`, `SearchFields`), all
optional:

| Setting | Replaces | Note |
|---|---|---|
| Titre pour les moteurs (70 max.) | The title, in `<title>` and `og:title` | The page keeps its title; 45 characters for Google to show it whole with the club name |
| Description pour les moteurs (300 max.) | The lead (chapô), cut to 160 characters | About 155 characters are shown |
| Image des aperçus de lien | The cover of a post, or the home picture | Landscape, 1,200 × 630 px ideally |
| Ne pas référencer | — | `noindex`, left out of `sitemap.xml` and `llms.txt` |

## Structured data (JSON-LD)

`App\Seo\StructuredData`, one `@graph` per page (`json_ld()`):

| Where | Types |
|---|---|
| Home | `Organization` (the club, `#organization`: logo `assets/images/brand/logo-512.png`, Facebook group and YouTube channel in `sameAs`) and `WebSite` |
| Post | `BlogPosting` (headline, description, picture, dates, author or the club, category, keywords) and `BreadcrumbList` |
| Page | `BreadcrumbList` |
| Post or page with FAQ blocks | One `FAQPage` with all their questions (answers keep their rich text) |
| Post or page with glossary blocks | One `DefinedTermSet` per glossary |

To check a page: https://search.google.com/test/rich-results and https://validator.schema.org.

## Files for crawlers

| Address | Content |
|---|---|
| `/robots.txt` | Every crawler allowed; `/admin`, `/mon-compte`, login, registration, password, membership payment and `/_toolkit` closed; address of the sitemap |
| `/sitemap.xml` | Home, news, membership, contact, "Mon premier vol" (`/premier-vol`), gliders, indexable pages and posts with their date of change (`App\Seo\Sitemap`) |
| `/llms.txt` | Summary of the club in Markdown, the indexable pages as a tree with their lead, the 20 latest posts ([llmstxt.org](https://llmstxt.org); no major engine has committed to reading it) |

The addresses are absolute, built from the request: behind a proxy, `TRUSTED_PROXIES` must be set
for them to start with `https://` (see [production](production.md)).

## Staging copy

`SEARCH_ENGINES_ALLOWED=0` (`App\Seo\SearchEngines::areAllowed()`) keeps search engines out of a copy
of the site shown to the committee: no page is indexable, `SearchEnginesListener` adds
`X-Robots-Tag: noindex, nofollow` to every response (media and documents included), `robots.txt` says
`Disallow: /`, `sitemap.xml` and `llms.txt` answer 404.

## Old addresses

The 301 redirects of the WordPress addresses (`redirect` table, `LegacyRedirectListener`) keep the
ranking of the current site: see [WordPress import](wordpress-import.md#redirects).

## Still to do

- New profiles of the club go in `StructuredData::PROFILES` (`Organization.sameAs`).
- `Course` for the training path and `Event` for public sessions, once their pages are public.
- Once online: Google Search Console and Bing Webmaster Tools (Bing also feeds Copilot and
  ChatGPT search), sitemap submitted to both, Lighthouse for performance.
