# Discovery Architecture Audit

**Date:** 2026-05-19  
**Branch:** `feature/faceted-discovery`  
**Scope:** Faceted discovery platform layer (Search API, Facets, Views, theme)  
**Method:** Evidence-only inspection of `config/sync`, `web/themes/custom/wcf_theme`, and setup scripts. No speculative removals.

## Executive summary

The discovery layer follows the intended split architecture: **SQL Views for bundle-specific listings** (`stallions`, `featured_content`) and **Search API + Facets for cross-bundle site search** (`search_stallions` at `/search`). Overlap in filter concepts (status, categories) is intentional and scoped to different routes—not duplicate platform layers.

## Views inventory

| View ID | Base | Route / use | Tag | Status |
|---------|------|-------------|-----|--------|
| `search_stallions` | `search_api_index_stallion_content` | `/search` (main menu) | wcf | Active |
| `stallions` | `node_field_data` | `/stallions` (main menu) | wcf | Active |
| `featured_content` | `node_field_data` | Paragraph blocks (`block_article`, `block_container_home`, …) | wcf | Active |
| `content_health` | `node_field_data` | `/admin/content/health/*` | wcf | Active |
| `media_governance` | `media_field_data` | `/admin/content/media/*` | wcf | Active |
| `moderated_content` | core | Admin moderation | — | Active |

**Finding:** No duplicate Search API–backed Views. Only one index-backed discovery View exists (`search_stallions`).

**Finding:** `stallions` and `search_stallions` both expose status/category filtering concepts but through different mechanisms (SQL exposed filters vs. Facets). Evidence:

- `views.view.stallions.yml` — exposed `field_status_value`, `field_category_target_id` on `/stallions`
- `views.view.search_stallions.yml` — keyword fulltext only; facets handle `stallion_status`, `categories`, `content_type` on `/search`
- `scripts/wcf-discovery-setup.php` documents intentional removal of duplicate exposed filters from `search_stallions`

**Recommendation:** Preserve both. Editorial training should clarify: **Stallions page = stallion-only SQL listing**; **Search = all indexed bundles with facets**.

## Search API

| Index | Server | Bundles | Facet source |
|-------|--------|---------|--------------|
| `stallion_content` | `wcf_database` | `stallion`, `container_home`, `article` | `search_api:views_page__search_stallions__page_1` |

**Finding:** Single index, single server—no duplicate indexes.

Processors enabled (evidence: `search_api.index.stallion_content.yml`): `entity_status`, `content_access`, `html_filter`, `ignorecase`, `transliteration`, `tokenizer` (min word size 3).

## Facets

| Facet ID | Field | Widget | Block |
|----------|-------|--------|-------|
| `stallion_status` | `status` | links | `wcf_facet_stallion_status` |
| `categories` | `category` | links | `wcf_facet_categories` |
| `content_type` | `type` | links | `wcf_facet_content_type` |

**Finding:** All three facets reference the same facet source and index. No orphaned facet config files in `config/sync`.

**Finding:** `facets_summary.search_active_filters` provides “Clear all filters” reset at `/search` (verified in rendered HTML when a facet is active).

## Theme libraries

| Library | CSS | JS | Loaded by |
|---------|-----|-----|-----------|
| `wcf_theme/global` | `css/style.css` | — | `wcf_theme.info.yml`, homepage preprocess |
| `wcf_theme/site-search` | — (depends on `global`) | — | `preprocess_views_view__search_stallions` |
| `wcf_theme/hero-slider` | — | `hero-slider.js` | `preprocess_field__node__field_hero_slides` |

**Finding:** No duplicate frontend CSS libraries. `site-search` correctly depends on `global` only.

## SCSS entry (`style.scss`)

All `@use` partials correspond to existing files under `src/scss/`:

- abstracts, utilities, layout, components (hero, stallion-card, featured-*), pages (front, stallion, stallion-listing, site-search)

**Finding:** No dead SCSS imports detected.

## Twig templates

| Template | Evidence of use |
|----------|-----------------|
| `views-view--search-stallions.html.twig` | `/search` page render (HTTP 200, classes present) |
| `views-view--stallions--page-1.html.twig` | `/stallions` — includes `stallion-listing.html.twig` component |
| `views-view--stallions--block-featured.html.twig` | Featured stallions paragraph block display |
| `views-view--stallions.html.twig` | Generic fallback only; **no display-specific route uses it** (page_1 and block_featured have more specific templates) |
| `components/stallion-listing.html.twig` | Used by stallions page template |

**Finding:** `views-view--stallions.html.twig` is a low-risk unused fallback. **Do not delete** without confirming no AJAX/unexpected display IDs invoke it.

## Preprocess hooks (`wcf_theme.theme`)

All hooks map to active bundles/displays:

| Hook | Purpose |
|------|---------|
| `preprocess_paragraph__featured_content` | Builds `featured_content` View |
| `preprocess_paragraph__featured_stallions` | Builds `stallions` block_featured View |
| `preprocess_views_view__search_stallions` | Attaches `site-search` library, layout classes |
| `preprocess_views_view__stallions` | Adds `stallion-listing` class |
| `preprocess_page__search` | `page--search` body class |
| `form_alter` (search-stallions exposed form) | ARIA, placeholder, reset class |

**Finding:** No unused preprocess hooks identified.

## Field render chains

| Context | View mode | Template chain |
|---------|-----------|----------------|
| Search — stallion | `card` | `node--stallion--card` → `stallion-card.html.twig` |
| Search — container_home | `teaser` | Core/default teaser (no theme override) |
| Search — article | `teaser` | Core/default teaser |
| Stallions listing | `card` | Same card chain |
| Featured stallions block | `card` | Same card chain |

**Finding:** Search results use **mixed render modes** by design (`views.view.search_stallions.yml` row config). Container homes in search render as generic teasers without `stallion-card` styling—not a duplicate chain, but an **inconsistent listing UX** between bundles on `/search`. Track for editorial/migration polish, not architecture removal.

## Block layout

Facet blocks and facets summary are placed in `wcf_theme` only, visibility restricted to `/search` and `/search*` (`block.block.wcf_facet_*.yml`, `block.block.wcf_search_facets_summary.yml`).

`page.html.twig` provides `layout-content--with-sidebar` when `sidebar_first` has blocks—verified on `/search` (sidebar + primary content grid).

## Items explicitly preserved

- Dual discovery paths (`/stallions` vs `/search`)
- Setup scripts (`scripts/wcf-discovery-setup.php`, `scripts/wcf-governance-setup.php`) as operational reference
- Standard Drupal disabled views (`archive`, `glossary`) — core scaffolding, not WCF duplication

## Recommended follow-ups (documentation only)

1. Editorial guideline: when to use `/stallions` vs `/search`
2. After migration volume increases: add `node--container_home--card` (or switch search row to `card`) for consistent search grid UX
3. Confirm `views-view--stallions.html.twig` remains fallback after any Views AJAX changes
