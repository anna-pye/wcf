# D7 Inner Page Parity Audit (Slice 6)

**Date:** 2026-05-20  
**Branch:** `feature/style-parity-assets-hardening`  
**Slice:** 6 — Inner page parity and footer/menu hardening

---

## Step 1 — Safety check

| Check | Result |
|-------|--------|
| Branch | `feature/style-parity-assets-hardening` |
| `git status --short` | Clean at slice start |
| `npm run build` | Success (before + after) |
| `ddev drush cr` | Success (before + after) |

---

## Step 2 — D7 reference (inner pages + footer)

### Stallion / product detail (D7)

| Selector / pattern | Purpose |
|--------------------|---------|
| `.group-header h3` | Page title — bold, left-aligned |
| `.group-left img` | Main image ~500px height, padded |
| `.about_container h6` | Section title — Bebas ~40px, bottom rule |
| `.gallery_container h6` | Gallery heading — Open Sans 26px `#747474` |
| `.gallery3 .image_holdar a` | Bordered gallery thumbs (~261px) |
| `.about_container p` | Body copy muted `#929292`, justified |
| `.texture_bg` / listing texture | Page band background (image asset) |

### Container home detail (D7)

| Selector / pattern | Purpose |
|--------------------|---------|
| `.container_homes_page` | Justified body, padded layout |
| `.container_homes_page img` | Floated main image ~380px |
| `.group-footer` | Thumbnail row with border |
| `.gallery_container_image img` | Gallery thumbs ~180px, bordered |

### Footer (D7 `page.tpl.php`)

| Column | Heading | Links (representative) |
|--------|---------|------------------------|
| 1 | Stallion | ASB Stallions, Stallions |
| 2 | (none) | Home, For Sale, Showcase, Foals, Contact Us |
| 3 | (none) | Agistment, Broodmares, Sold, Sold Stallions, Nomination, About, Container Homes |
| 4 | — | Web site contact, logo, copyright |

**CSS:** `.navigation-footer`, `.footer_heading` / `h6`, `foo-bullet.png` (deferred in D11)

---

## Step 3 — D11 baseline (pre-slice)

| Area | State |
|------|-------|
| `node--stallion--full.html.twig` | Semantic sections; generic title sizing |
| `node--container-home--full.html.twig` | Present; mirrored stallion layout |
| Footer | Flat `footer` menu in one grid; no column headings |
| Inner chrome | `site-chrome--inner` grey band from Slice 5 |
| Assets | Text branding only; no `logo.svg` |

---

## Step 4 — Implementation (Slice 6)

| Deliverable | Approach |
|-------------|----------|
| Stallion full parity | Shared `_inner-profile.scss`: display title + bottom rule, main image max-height, justified muted body, bordered gallery grid |
| Container home full parity | Same shared profile SCSS; container-specific status/tags in `_container-home.scss` |
| Footer columns | `menu--footer.html.twig` + `wcf_theme_preprocess_menu()` — three columns (Stallions / Site / Services) mapped from current flat menu titles |
| Header polish | Inner layout modifier `layout-container--inner`; desktop nav spacing in `_chrome.scss` |
| Assets | No new image refs; CSS bullets; listing band via `$listing-band-bg` |

**Out of scope (unchanged)**

- Migration logic, custom modules (except theme preprocess for footer columns)
- New JavaScript
- D7 footer link inventory expansion (requires menu config / editorial)
- Approved `logo.png`, texture JPGs, webfonts

---

## Files changed

| File | Change |
|------|--------|
| `web/themes/custom/wcf_theme/src/scss/pages/_inner-profile.scss` | **New** — shared profile layout |
| `web/themes/custom/wcf_theme/src/scss/pages/_stallion.scss` | Stallion-specific; removed duplicated layout |
| `web/themes/custom/wcf_theme/src/scss/pages/_container-home.scss` | Container-specific only |
| `web/themes/custom/wcf_theme/src/scss/layout/_chrome.scss` | Footer columns + inner nav polish |
| `web/themes/custom/wcf_theme/src/scss/style.scss` | Import `inner-profile` |
| `web/themes/custom/wcf_theme/templates/navigation/menu--footer.html.twig` | **New** — D7 column markup |
| `web/themes/custom/wcf_theme/templates/layout/page.html.twig` | `layout-container--inner` |
| `web/themes/custom/wcf_theme/wcf_theme.theme` | `preprocess_menu()` footer columns |
| `web/themes/custom/wcf_theme/css/style.css` | Rebuilt |

**Not changed:** node field config, migrations, JS libraries, `page--front.html.twig` (retains `--front` modifier only).

---

## Step 5–6 — QA

| URL / check | HTTP | Markup / notes |
|-------------|------|----------------|
| `/` | 200 | `layout-container--front`; `site-footer__columns` × 3 headings |
| `/stallions` | 200 | `layout-container--inner`, `site-chrome--inner` |
| `/search` | 200 | `layout-container--inner`, `site-search` |
| `/stallions/unnamed-out-of-caramida-de-aur` (nid 257) | 200 | `stallion__title`, `stallion__gallery` |
| Container home full | **403** anonymous | No published `container_home` nodes; nid 34/35 unpublished |
| `logo.svg` in HTML | Pass | Not referenced on sampled routes |
| Breakpoints 375 / 768 / 1200 | **Manual** | Responsive grid + `<details>` nav; automated curl only |

### Footer column mapping (D11 menu → D7-like groups)

| Column | Items |
|--------|-------|
| Stallions | Stallions, Search |
| Site | Home, Contact |
| Services | Nomination, Facebook |

---

## Remaining blockers

1. **Container home full QA** — publish or grant preview access to at least one `container_home` node for visual sign-off  
2. **Footer link parity** — D7 had ~15+ footer links across three columns; D11 simplified menu (6 items) — expand `footer` menu in config when scope approved  
3. **Approved assets** — logo, `foo-bullet.png`, listing/about texture backgrounds  
4. **Licensed fonts** — Bebas / Open Sans / Proxima stand-ins remain system stacks  
5. **Pixel screenshots** — manual browser pass at 375 / 768 / 1200 recommended  

---

## Recommended next slice

**Slice 7:** Expand footer/main menu config to approved D7 destinations; optional listing texture band when asset approved; container home publish + screenshot sign-off.

---

# Slice 6A — Stallion / horse detail parity (2026-05-20)

**D7 reference:** `/product/view/donna` — `product_view.tpl.php` + `.details h5` / `h6`, `.tabber` year pills, `.gallery_container`.

## Safety check (Slice 6A start)

| Check | Result |
|-------|--------|
| Branch | `feature/style-parity-assets-hardening` |
| Non-theme files in tree | `docs/d7-homepage-visual-reconstruction-audit.md` (modified) — pre-existing on branch; theme slice continued |

## D11 fields audit (`stallion` bundle)

| Purpose | D11 field | Notes |
|---------|-----------|--------|
| Horse display name (e.g. Donna) | `field_display_name` | D7 `product_name`; mapped in `wcf_d7_node_stallion`; backfill `scripts/wcf-map-product-display-names.php` |
| Descriptive subtitle line | `title` (node label) | e.g. `2025 cremello QH / SH x TB filly` |
| Body / description | `body` | Migrated from D7 `description` |
| Main image | `field_main_image` | Media reference; alt populated from D7 `product_name` at media layer only |
| Gallery | `field_gallery` | Multi media ref; **0 rows** populated site-wide post-migration |
| Documents | `field_documents` | PDF media refs |
| Video | `field_video` | Remote video media |
| Category (Foals, etc.) | `field_category` | Taxonomy `categories`; Donna = term **Foals** (tid 2) |
| Foal year | **Missing** | D7 `wcf_product.year`; not mapped in `wcf_d7_node_stallion.yml` |
| Status | `field_status` | active / sold / retired |
| Featured | `field_featured` | Listing sort only |

**View displays:** `default`, `card`, `teaser`, `hero` — full layout driven by `node--stallion--full.html.twig` (fields hidden/rendered in template).

## D7 vs D11 — Donna (nid 320, `/stallions/donna`)

| D7 | D11 |
|----|-----|
| `h5` → `product_name` → **DONNA** | Not on node; only in main image **alt** (`"Donna'`) from `WcfD7ProductFile::lookupAltText()` |
| `h6` → `title_with_year` | Node `title` / `<h1 class="stallion__subtitle--primary">` |
| Gallery images `product_img1–4` | `field_gallery` empty (0 stallions with gallery rows) |
| Foals year `.tabber` pills | **Blocked** — no year field / no View year exposed filter |
| Quote band (`field_data_body` entity 19) | **Not migrated** — no D11 block or node equivalent located |

## Template changes (Slice 6A)

| File | Change |
|------|--------|
| `templates/node/node--stallion--full.html.twig` | D7 layout: optional foal year nav; image left / copy right; quoted name + subtitle when `stallion_display_name` exists; body in intro column; `isEmpty()` guards for gallery/video/documents; grey gallery band; removed categories block from detail |
| `wcf_theme.theme` | `preprocess_node__stallion` full mode: `stallion_display_name` (NULL), `stallion_is_foals`, `stallion_foal_year_links` ([]) |
| `src/scss/pages/_stallion.scss` | D7 name/subtitle colors, year pills, gallery band, narrower profile width |
| `src/scss/pages/_inner-profile.scss` | Body copy on `.stallion__body`; removed `.stallion__title` display heading mixin |
| `css/style.css` | Rebuilt |

## SCSS (Slice 6A)

- Narrow profile: `max-width: 56rem` on `.stallion`
- Quoted name: `.stallion__name` + Unicode quote pseudo-elements
- Subtitle: `#db4b3d`, 20px scale — `.stallion__subtitle` / `--primary` when no separate name
- Year pills: `.stallion__year-nav-link` — teal gradient circles (D7 `.tabber`)
- Gallery band: `.stallion__gallery-band` on `$color-d7-inner-band`

## Foals year selector status

**Not implemented (data blocker).**

Minimal model to unblock:

1. `field_foal_year` (integer or list) or taxonomy vocabulary **Foal year**
2. Map `wcf_product.year` in `wcf_d7_node_stallion.yml`
3. Views exposed filter or contextual filter on `stallions` for category Foals + year
4. Preprocess: build `stallion_foal_year_links` from distinct years (D7 SQL grouped by year)

Until then, template renders year nav only when `stallion_foal_year_links` is non-empty.

## Gallery status

- Template and SCSS ready (grey band + thumb grid from Slice 6)
- **No content:** `SELECT COUNT(*) FROM node__field_gallery WHERE bundle='stallion'` → **0**
- Remediation: re-run or fix `wcf_d7_node_stallion` gallery `sub_process` + confirm D7 `product_img1–4` filenames resolve via `wcf_d7_media_image_product`

## Horse display name (implemented 2026-05-20)

- **Field:** `field_display_name` on `stallion` (config/sync)
- **Migration:** `display_name` ← `WcfD7Product::normalizeDisplayName(product_name)` in `wcf_d7_node_stallion.yml`
- **Backfill:** `ddev drush php:script scripts/wcf-map-product-display-names.php` (requires `migrate` DB connection)
- **Theme:** `stallion_display_name` in `preprocess_node__stallion` → quoted `.stallion__name` in full template

## QA (automated curl — 2026-05-20)

| URL | HTTP | Notes |
|-----|------|-------|
| `/` | 200 | Homepage / featured stallions unchanged |
| `/stallions` | 200 | Listing + filters |
| `/search` | 200 | Search |
| `/stallions/donna` | 200 | `stallion__subtitle--primary`; no empty gallery/documents/categories sections |
| `/stallions/unnamed-out-of-caramida-de-aur` | 200 | Second stallion sample |

**Manual:** 375 / 768 / 1200 px overflow, quoted name when field exists, gallery when data exists.

## Slice 6A blockers

1. ~~**Horse display name backfill**~~ — **Resolved Slice 7** (see below)  
2. **Gallery data** — zero migrated gallery references  
3. **Foal year filter** — no `year` field; year nav not rendered  
4. **Quote intro** — D7 `field_data_body` entity 19 not in D11; needs block or basic page  
5. **Container home full** — still 403 anonymous (unchanged from Slice 6)

---

# Slice 7 — Horse/Stallion node data and display parity (2026-05-20)

**Branch:** `feature/style-parity-assets-hardening`

## Step 1 — Safety check

| Check | Result |
|-------|--------|
| Branch | `feature/style-parity-assets-hardening` |
| Expected theme/migration/config changes | Present (stallion fields, `wcf_migrate`, `wcf_theme`, `config/sync`) |
| **Unexpected / out-of-slice** | `M .ddev/config.yaml`, `?? web/sites/default/settings.migrate.php`, `?? scripts/wcf-import-legacy-database.sh`, `M docs/d7-homepage-visual-reconstruction-audit.md` |

Slice continued after noting extras; no edits to `.ddev` or migrate settings in this pass.

## Step 2 — D11 `stallion` fields (`ddev drush field:info node stallion`)

| Purpose | Field | D7 source |
|---------|-------|-----------|
| Horse display name | `field_display_name` | `wcf_product.product_name` |
| Descriptive subtitle | `title` | `title_with_year` |
| Body | `body` | `description` |
| Category | `field_category` | `category_id` → taxonomy `categories` |
| Main image | `field_main_image` | `product_img` → media |
| Gallery | `field_gallery` | `product_img1`–`product_img4` → media |
| Documents | `field_documents` | `pdf_file` → media |
| Video | `field_video` | YouTube iframes |
| Status / featured | `field_status`, `field_featured` | `sold`, — |
| Foal year | **Missing** | `wcf_product.year` |

**View display:** `field_display_name` hidden on `default` (rendered in Twig). Full layout: `node--stallion--full.html.twig` + `_inner-profile.scss` / `_stallion.scss`.

## Step 3 — Donna sample (nid 320, `/stallions/donna`)

| Item | D7 (`wcf_product` id 320) | D11 (after Slice 7 QA) |
|------|---------------------------|-------------------------|
| Horse name | `"Donna'` (legacy quotes) | `field_display_name` = **Donna** |
| Subtitle | `2025 cremello QH / SH x TB filly` | Node `title` → `.stallion__subtitle` |
| Category | `category_id` 1 (Foals) | Term **Foals** (tid 2) |
| Foal year | `year` = **2025** | **Not stored** on node |
| Main image | `414447IMG_20250928_100411.jpg` | `field_main_image` mid **357** |
| Gallery | 4 filenames (`625431_C5J0068.jpg`, …) | **`field_gallery` count 0** |
| Documents | (none on row) | 0 |

**Display name backfill (2026-05-20):** `ddev drush php:script scripts/wcf-map-product-display-names.php` → 275 nodes set, 1 already populated (Donna), 2 skipped empty legacy name → **276 / 276** stallions with `field_display_name` in DB.

## Step 4 — Theme / template (no new field invented)

Already on branch:

- Quoted horse name: `.stallion__name` + `stallion_display_name` preprocess  
- Subtitle: node `label` in `.stallion__subtitle`  
- Two-column header: `_inner-profile.scss` grid (image left, copy right)  
- Gallery band: `.stallion__gallery-band` (renders only when `field_gallery` non-empty)  
- Category: not output on full view (avoids duplicate taxonomy block; listing uses Views/facets)

## Step 6 — Foal year selector

**D7 evidence:** `product_view.tpl.php` — year pills from `wcf_product.year` grouped for `category.alias = 'foals'` (15+ distinct years in legacy DB, e.g. 2025→6 rows).

**D11:** No `field_foal_year` (or equivalent). `stallion_foal_year_links` remains `[]`; template nav hidden.

**Safest unblock (deferred):**

1. Add `field_foal_year` (integer, year list) on `stallion`  
2. Map `year` in `wcf_d7_node_stallion.yml` (after D7 column audit — already documented in `stallion-migration-audit.md`)  
3. Views exposed/contextual filter on `/stallions` for Foals + year  
4. Preprocess: build `stallion_foal_year_links` from distinct published years (no JS)

## Step 7–8 — Build / cache

| Command | Result |
|---------|--------|
| `npm --prefix web/themes/custom/wcf_theme run build` | Success |
| `ddev drush cr` | Success |

## Step 9 — Automated QA (curl via DDEV)

| URL | HTTP | Markup notes |
|-----|------|----------------|
| `/` | 200 | — |
| `/stallions` | 200 | — |
| `/search` | 200 | — |
| `/stallions/donna` | 200 | `stallion__name` → Donna; `stallion__subtitle` → 2025 cremello…; no gallery/year nav |
| `/stallions/unnamed-out-of-caramida-de-aur` | 200 | `stallion__name` populated from display name |

**Manual:** 375 / 768 / 1200 px layout, quoted name typography, gallery band when data exists.

## Slice 7 blockers

1. **Gallery migration** — D7 Donna has 4 gallery files; D11 `node__field_gallery` empty site-wide (0 rows). Template/SCSS ready; remediation = audit `wcf_d7_media_image_product` lookup for `product_img1–4` filenames (migration YAML already maps `gallery_files`).  
2. **Foal year** — D7 `year` column populated; no D11 field; year nav not rendered.  
3. **Quote intro band** — D7 `field_data_body` entity 19 not migrated.  
4. **Container home full** — unchanged (403 anonymous).

## Files touched (Slice 7 pass)

| File | Change |
|------|--------|
| `docs/d7-inner-page-parity-audit.md` | This Slice 7 report |
| *(data only)* | `node__field_display_name` backfill via `scripts/wcf-map-product-display-names.php` |

No migration YAML changes in this slice (audit confirms mapping exists; gallery needs operational re-import investigation, not new field guesses).
