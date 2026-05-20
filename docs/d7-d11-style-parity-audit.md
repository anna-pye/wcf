# D7 → D11 Style Parity Audit

**Date:** 2026-05-20  
**Branch:** `feature/d7-d11-style-parity-audit`  
**Scope:** Audit and plan only — no content migration, no blind D7 CSS port.

## Executive summary

Drupal 11 uses a modern SCSS pipeline (`src/scss/` → `css/style.css` via `npm run build`). Twig components and view modes are already in place for stallions, homepage paragraphs, and shared cards. Legacy D7 styling is available in `~/drupal7-legacy/sites/all/themes/wcf/` (not in this repo). **No screenshots or design exports exist in `drupal11-upgrade`**, so pixel-perfect parity requires either live D7 comparison or archived theme assets/fonts.

This audit maps D7 visual references to D11 hooks and proposes phased implementation without duplicating logic or copying obsolete Bootstrap 2 / jQuery UI patterns.

---

## 1. Current D11 theme structure

### Theme metadata

| Item | Path / value |
|------|----------------|
| Theme | `web/themes/custom/wcf_theme` |
| Base theme | none (`base theme: false`) |
| Global library | `wcf_theme/global` → `css/style.css` |
| Conditional libraries | `hero-slider`, `site-search` |
| Theme logic | `wcf_theme.theme` (preprocess, CTA helpers, form alters) |
| Build | `npm run build` / `npm run watch` in theme dir (`sass` CLI) |

### SCSS architecture (actual paths — not flat `scss/` root)

```
src/scss/
  style.scss                 # entry
  abstracts/_variables.scss  # design tokens (partial)
  layout/_page.scss          # layout-container, header, main, content width
  components/                # hero, cards, forms, featured sections
  pages/                     # front, stallion, stallion-listing, site-search
  utilities/_visually-hidden.scss
```

**Note:** Task spec suggested `scss/_tokens.scss` at theme root. This project uses **`abstracts/_variables.scss`** under `src/scss/`. Future tokens belong there (or `abstracts/_tokens.scss` imported from `style.scss`).

### Compiled output

- `css/style.css` (committed; rebuild after SCSS edits)
- `css/style.css.map` may exist locally; build script uses `--no-source-map`

### Twig templates (27 files)

| Category | Files |
|----------|--------|
| Layout | `templates/layout/page.html.twig`, `page--front.html.twig` |
| Node | `node--homepage--full`, `node--stallion--full`, `node--stallion--teaser`, `node--stallion--card`, `node--stallion--hero`, `node--container-home--card` |
| Components | `stallion-card`, `stallion-listing`, `feature-card`, `featured-*-section`, `testimonial-card` |
| Paragraphs | `hero-slide`, `feature-card`, `featured-stallions`, `featured-content`, `testimonial-item` |
| Views | `views-view--stallions*`, `views-view--search-stallions` |
| Fields | homepage hero, feature cards/sections, testimonials |

**Missing (intentional or gap):**

- `node--container-home--full.html.twig` — full page uses core/default field layout + `default` view display
- `node--stallion--teaser.html.twig` exists but **listings use `card` view mode**, not teaser
- `templates/page.html.twig` — not used; layout lives under `templates/layout/`

### View modes & displays (config/sync)

| Bundle | View modes configured | Theme override |
|--------|----------------------|----------------|
| `stallion` | default, card, hero, teaser | full, card, hero, teaser templates |
| `container_home` | default, card, teaser | card only (`node--container-home--card`) |
| `homepage` | default, full | `node--homepage--full` |

Canonical node routes use view mode **`full`**. Stallion full layout is overridden in Twig; `container_home` full is **not** overridden.

### Views

| View | Purpose | Theme templates |
|------|---------|-----------------|
| `stallions` | Listing, featured block | `views-view--stallions*.html.twig`, `stallion-listing` component |
| `search_stallions` | Keyword search | `views-view--search-stallions.html.twig` |
| `featured_content` | Paragraph-driven listings by bundle | Uses view modes from display config |

### Design tokens today (`abstracts/_variables.scss`)

- Text `#1a1a1a`, primary `#2d5016` (forest green — **differs from D7 bright green/teal**)
- System font stack (no Open Sans / Bebas / Proxima loaded)
- Spacing scale xs–xl, breakpoints 48rem / 75rem, content max 72rem

### Repeated patterns

- Card grid: `.stallion-card` (4:3 media, shared by stallion + container_home card modes)
- Hero stack: `.hero-slide` / `.stallion-hero` (gradient overlay, CTA classes)
- CTAs: `button`, `button--primary` via `wcf_theme.theme` preprocess (not duplicated in Twig)
- Forms: `_forms.scss` targets webform machine names

### Block / region gaps

- `wcf_theme_mainnavigation` → `primary_menu`
- `wcf_theme_footer` → `footer`
- **No `system_branding` block** exported for `wcf_theme` — header region may be empty until branding is configured (D7 used `.logo` in banner overlay)

---

## 2. Available D7 styling references

### In `~/drupal7-legacy` (authoritative)

| Asset | Path |
|-------|------|
| Primary custom CSS | `sites/all/themes/wcf/css/custom.css` (~1.8k lines; dated snapshots in `custom_*.css`) |
| Theme CSS | `style.css`, `bootstrap-extensions.css`, `r_c.css`, `flexslider.css`, `responsiveslides.css` |
| Templates | `page.tpl.php`, `page--front.tpl.php`, `node.tpl.php`, `html.tpl.php` |
| Fonts | `sites/all/themes/wcf/fonts/` (Open Sans, Proxima Nova, Bebas Neue, Helvetica) |
| Images | `sites/all/themes/wcf/images/` (textures, icons, homepage promos) |
| JS (obsolete for D11) | jQuery 1.8, Flexslider, Fancybox, ResponsiveSlides, Bootstrap 2 |

### In `drupal11-upgrade`

- **No** D7 theme copy, **no** screenshots, **no** PNG/JPG reference assets
- Related doc: `docs/frontend-consistency-audit.md` (cross-surface consistency, not D7 parity)

### Blocker for exact visual match

Without **live D7 URL**, **screenshots**, or **font files copied into D11 theme**, color/type/spacing can only be approximated from CSS parsing. Recommend capturing:

1. Homepage (hero + featured stallions + container promo band)
2. Stallion listing + single stallion + gallery
3. Container homes listing + single
4. Footer + primary nav + contact/webform
5. Mobile breakpoints (375px, 768px)

---

## 3. Parity matrix

| # | Visual area | D7 selector / reference | Proposed D11 component / template | SCSS target | Risk | Priority |
|---|-------------|-------------------------|-----------------------------------|-------------|------|----------|
| 1 | Header / logo / social | `.banner_bg`, `.logo`, `.facebook`, `.address` — `custom.css` L214–237 | `layout/page.html.twig` `.site-header`; block branding TBD | `layout/_page.scss`, future `layout/_header.scss` | **High** — no branding block; D7 overlay layout ≠ D11 stack | P1 |
| 2 | Primary navigation | Bootstrap nav in `page.tpl.php`; multi-level hardcoded + menu | `system_menu_block:main` in `primary_menu` | `layout/_page.scss` (menu list styles) | Med | P1 |
| 3 | Footer | `.navigation-footer`, `.footer_heading`, `foo-bullet.png` | `page.footer` + `wcf_theme_footer` | `layout/_page.scss` | Low | P2 |
| 4 | Homepage hero / slider | `#slider1` ResponsiveSlides, `.banner_text`, `.flexslider` | Paragraph `hero_slide` + `hero-slider` JS | `components/_hero-slider.scss`, `_hero-slide.scss` | Med — behavior modernized; visual tune | P1 |
| 5 | Homepage sections | `.stallions h2`, `.news_sec`, raw SQL product grids on `page--front.tpl.php` | Homepage node fields + paragraphs (`featured_stallions`, `feature_card`, etc.) | `pages/_front.scss`, component SCSS | **High** — structure differs (no SQL grids) | P2 |
| 6 | Stallion full page | `.fancybox_container`, `.gallery_container`, `.about_container` | `node--stallion--full.html.twig` | `pages/_stallion.scss` | Med | P1 |
| 7 | Stallion cards / listings | `.view-stalions .border_div1`, `.stallion_title a`, `.read_more` | `stallion-card` + Views `card` mode | `components/_stallion-card.scss`, `_stallion-listing.scss` | Low — structure aligned | P1 |
| 8 | Container home full | `.container_homes_page`, `.group-header`, `.gallery_container` | Default node + fields (no full Twig yet) | New `pages/_container-home.scss` when template added | **High** — no full template | P2 |
| 9 | Container home cards | `.view-container-homes` (mirrors stallion listing) | `node--container-home--card` → `stallion-card` | `_stallion-card.scss` (shared) | Low | P1 |
| 10 | Basic pages | `.about_container`, `.row-container` | Core `page` content region | `layout/_page.scss` | Low | P3 |
| 11 | Forms / CTAs | `.input_type input[type=submit]` — `#2bb99b` / `#015c49` | Webforms + `.button--primary` | `components/_forms.scss`, `_buttons.scss` | Med | P1 |
| 12 | CTA buttons (inline) | `.read_more`, `.got_it a`, teal borders `#7de1cb` | `button`, `button--primary`, future `button--outline` | `components/_buttons.scss` | Low | P1 |
| 13 | Image galleries | `.gallery3 .image_holdar a` bordered thumbs, Fancybox | Media fields + responsive styles; no Fancybox | `pages/_stallion.scss` or new `components/_media-gallery.scss` | Med — lightbox behavior differs | P2 |
| 14 | Search | N/A (D7 separate paths) | `views-view--search-stallions` | `pages/_site-search.scss` | Low | P2 |
| 15 | Typography | `bebas_neueregular` H2s, `open_sansregular` body | System stack today | `abstracts/_variables.scss`, `@font-face` slice | Med — licensing/hosting | P1 |

### D7 color samples (from `custom.css` — for token plan)

| Usage | D7 value |
|-------|----------|
| Brand green (banner) | `#2eb361` |
| Accent teal | `#6fbcac` |
| CTA fill | `#2bb99b` |
| CTA text | `#015c49` |
| Accent red | `#e8463a` |
| Card title | `#8c8c8c` |
| Body muted | `#9d9d9d` |
| Read-more border | `#7de1cb` |

D11 current primary `#2d5016` is **not** in D7 palette above — intentional token swap required in a later slice.

---

## 4. Template review (Task 6)

| Template | Status | Notes |
|----------|--------|-------|
| `node--stallion--full.html.twig` | **Keep** | Semantic sections, accessible headings, CTA via preprocess |
| `node--stallion--teaser.html.twig` | **Keep** | Delegates to `stallion-card`; low traffic if only `card` used in Views |
| `node--container-home--full.html.twig` | **Missing** | Add in slice 2 when mirroring stallion profile layout |
| `node--container-home--card.html.twig` | **Keep** | Correct reuse of `stallion-card` |
| `layout/page.html.twig` | **Keep** | Landmarks, sidebar for facets |
| `layout/page--front.html.twig` | **Keep** | Hero-first chromeless main |

**No template rewrites in this pass.**

---

## 5. Implementation log (this pass)

### Files changed

| File | Change |
|------|--------|
| `docs/d7-d11-style-parity-audit.md` | Created (this document) |
| `docs/d7-d11-style-system-plan.md` | Created |
| `web/themes/custom/wcf_theme/src/scss/abstracts/_variables.scss` | D7 reference tokens; radius, font stacks |
| `web/themes/custom/wcf_theme/src/scss/components/_buttons.scss` | Shared `.button` system |
| `web/themes/custom/wcf_theme/src/scss/style.scss` | Import buttons; remove empty stubs |
| `web/themes/custom/wcf_theme/css/style.css` | Rebuilt via `npm run build` |

### Files intentionally not changed

- All Twig templates
- `wcf_theme.theme`, `wcf_theme.libraries.yml`
- Config exports (`config/sync/*`)
- D7 legacy repo
- Card/hero/stallion component SCSS (slice 1 targets)
- No new build tooling

### Visual parity now possible

- Unified focus/hover for `.button--primary` on hero, feature cards, stallion nomination, webforms
- Documented mapping from D7 CSS → D11 SCSS targets
- Shared card component already supports stallion + container_home

### Still requires D7 screenshots / theme files

- Header/logo overlay vs stacked header
- Exact typography (webfont files not in D11 theme)
- Listing texture backgrounds (`listing_texture_bg.jpg`)
- Gallery lightbox behavior and bordered thumb grid
- Homepage promo bands that were SQL-driven in D7
- Pixel confirmation of green vs forest primary

### Risks

| Risk | Mitigation |
|------|------------|
| Token drift (D11 `#2d5016` vs D7 teal) | Phased token activation in slice 1 after visual sign-off |
| Empty header region | Add `block.block.wcf_theme_site_branding.yml` + styles in slice 1 |
| container_home full layout generic | Add Twig + SCSS in slice 2 |
| Fancybox → core/media | Use responsive gallery + optional GLightbox module later, not D7 JS |
| Committing compiled CSS without build | Always run `npm run build` in theme |

### Next recommended implementation slice

**Slice 1 — Brand chrome + tokens:** Load licensed/self-hosted fonts, switch `$color-primary` to approved D7 mapping, style `.site-header` / `.site-footer` / menus, add site branding block config.

---

## Slice 1 implementation log

**Date:** 2026-05-20  
**Branch:** `feature/d7-d11-style-parity-audit`

### Files changed

| File | Change |
|------|--------|
| `config/sync/block.block.wcf_theme_site_branding.yml` | **Added** — `system_branding_block` in `header` region, weight `-10` |
| `web/themes/custom/wcf_theme/src/scss/abstracts/_fonts.scss` | **Added** — commented `@font-face` scaffold (Open Sans, Bebas Neue, Proxima Nova) |
| `web/themes/custom/wcf_theme/src/scss/layout/_page.scss` | **Updated** — header, branding, primary nav, footer chrome |
| `web/themes/custom/wcf_theme/src/scss/style.scss` | **Updated** — `@use 'abstracts/fonts'` after variables |
| `web/themes/custom/wcf_theme/css/style.css` | **Rebuilt** via `npm run build` |
| `docs/d7-d11-style-parity-audit.md` | This section |

### Branding block

- **Status:** Did not exist for `wcf_theme` (only `olivero_site_branding` in config). **Added** `block.block.wcf_theme_site_branding.yml`.
- Logo + site name enabled; slogan disabled until copy is confirmed.
- Existing `wcf_theme_mainnavigation` and `wcf_theme_footer` blocks unchanged.

### Font scaffold

- `_fonts.scss` documents licensing and WOFF2 placement under `fonts/`; all `@font-face` rules remain commented.
- Active UI still uses `$font-family-base` system stack; no font files copied from D7.

### Header / footer / menu styling summary

- **Mobile-first** `.site-header` flex stack; row layout from `$breakpoint-md`.
- **Branding:** `.site-branding`, `__logo`, `__name` — logo max dimensions, site name visible (not hidden when logo present).
- **Navigation:** `.site-header__menus` + `.primary-menu` selectors (core outputs `.menu--main` inside region wrapper).
- **Touch / a11y:** 44px min link height, `:focus-visible` rings using `$color-primary`, hover states without `!important`.
- **Footer:** `.site-footer` band + link styles; footer menu list flex layout.
- **`$color-primary`** left at `#2d5016` (forest green); D7 reference tokens used only for muted footer background tint.

### Intentionally not changed

- All Twig templates
- `wcf_theme.theme`, `wcf_theme.libraries.yml`
- `$color-primary` activation to D7 greens
- Component SCSS (`_buttons`, cards, hero, forms) beyond prior slice 0 work on branch
- D7 `custom.css`, JS, Bootstrap 2, Fancybox, font binaries
- Content migration and custom modules

### Remaining blockers

- [ ] D7 side-by-side screenshots (homepage, stallion, listing, footer, mobile)
- [ ] Canonical brand green decision (`#2eb361` / `#2bb99b` vs `#2d5016`)
- [ ] Font licensing confirmation and WOFF2 placement
- [ ] Logo/header asset confirmation (dimensions, retina, front-page overlay behavior)

---

## Slice 2 implementation log

**Date:** 2026-05-20  
**Branch:** `feature/d7-d11-style-parity-audit`

### Field / display audit findings

| Field | Machine name | Full (`default`) display | Notes |
|-------|----------------|------------------------|-------|
| Title | `title` (base) | Via template `label` | No duplicate page-title block on `wcf_theme` |
| Body | `body` | `text_default`, label hidden | Summary enabled on field |
| Main image | `field_main_image` | Media `default` view mode, label hidden | Optional |
| Gallery | `field_gallery` | Media `default`, label above | Multi-value media (image) |
| Sold status | `field_sold` | Boolean, label inline | **Not** rendered in Twig; `container_home_status_label` from preprocess when sold |
| Categories | `field_category` | Taxonomy `entity_reference_label`, links | No separate “tags” field |

**View modes in config:** `default`, `card`, `teaser` — no `full` display config; canonical route uses view mode `full` with Twig suggestion `node--container-home--full`, falling back to `default` field formatters.

**Not on bundle:** `field_cta_phone`, `field_video`, `field_documents`, `field_featured`, `field_status` (stallion-only).

### Template added

`web/themes/custom/wcf_theme/templates/node/node--container-home--full.html.twig`

- Sections: hero/media, title + sold status, body summary, gallery (if populated), categories meta/tags
- No CTA/contact section (no supporting field or dedicated route on bundle)
- No JavaScript; rendered fields only for cacheability

### SCSS added

`web/themes/custom/wcf_theme/src/scss/pages/_container-home.scss` — imported from `style.scss`

- Mobile-first hero grid aligned with stallion full page
- Responsive gallery grid (same minmax pattern as `.stallion__gallery`)
- Category chip links with focus-visible states
- No shared `_media-gallery.scss` (stallion gallery left unchanged; duplication avoided)

### Files changed

| File | Change |
|------|--------|
| `web/themes/custom/wcf_theme/templates/node/node--container-home--full.html.twig` | **Added** |
| `web/themes/custom/wcf_theme/src/scss/pages/_container-home.scss` | **Added** |
| `web/themes/custom/wcf_theme/src/scss/style.scss` | Import `pages/container-home` |
| `web/themes/custom/wcf_theme/css/style.css` | Rebuilt |
| `docs/d7-d11-style-parity-audit.md` | This section |

### Visual parity improvements

- Full container home pages use structured profile layout instead of generic field stack
- Sold badge, gallery grid, and category links styled consistently with stallion full patterns
- `container-home--no-media` modifier when main image is empty

### Intentionally not changed

- `node--container-home--card.html.twig`, `stallion-card` component
- Stallion templates and `_stallion.scss` gallery rules
- `wcf_theme.theme` (existing `container_home_status_label` sufficient)
- Entity view displays, migrations, custom modules
- Fancybox / D7 gallery JS
- `_media-gallery.scss` shared partial

### Remaining blockers

- [ ] D7 vs D11 screenshots for container home full + listing
- [ ] Optional: `field_main_image` hero view mode on default display (stallion uses `hero`; container_home still `default`)
- [ ] Gallery lightbox behavior if required (not D7 Fancybox port)
- [ ] Listing texture / card border parity from D7 `.view-container-homes`

### Slice 2 visual QA (local)

**Date:** 2026-05-20  
**Branch:** `feature/d7-d11-style-parity-audit`

| Item | Detail |
|------|--------|
| Test node | **Created** nid `324` — “Temporary Container Home Style Test” (published; no media, gallery, or categories) |
| Existing nodes | nid 34–35 unpublished only — not used |
| Path tested | `/node/324`, alias `/container-homes/temporary-container-home-style-test` |
| Login URL | `ddev drush uli /node/324` (one-time; not stored) |
| Viewports | Desktop 1200×900, tablet 768×1024, mobile 375×812 (Playwright headless + DOM checks) |

**Checks completed**

| Check | Result |
|-------|--------|
| Title spacing | Pass — single `h1`, margin consistent |
| No-media layout | Pass after fix — `container-home--no-media`, hero single column |
| Body readability | Pass — summary section ~65ch max-width |
| Category/meta | Not exercised (empty on test node) |
| Gallery | Not exercised (empty on test node) |
| Header/footer | Pass — branding block + footer nav present; no overlap |
| Duplicate title | Pass — no `.page-title` block |
| Raw field labels | Pass — none in output |
| Empty wrappers | **Fail → fixed** — empty `.container-home__media` rendered when main image empty |

**Defect found**

- Twig `{% if content.field_main_image %}` is truthy for an empty field render array, so no-media pages output an empty `.container-home__media` div (extra grid gap risk at tablet/desktop).

**Fix applied**

- `node--container-home--full.html.twig`: use `{% if not node.field_main_image.isEmpty() %}` for the media wrapper (matches `container-home--no-media` class logic).

**Commands run**

```bash
git status --short && git branch --show-current
ddev drush sql:query "SELECT nid, title, status FROM node_field_data WHERE type = 'container_home' ..."
ddev drush php:eval "… Node::create container_home …"   # nid 324
cd web/themes/custom/wcf_theme && npm run build
ddev drush cr
ddev drush uli /node/324
```

`ddev drush cim -y` **not run** — `block.block.wcf_theme_site_branding` already active locally.

**Build / cache**

- `npm run build` — pass
- `ddev drush cr` — pass

**Remaining visual blockers (post-QA)**

- Gallery grid and category chips not verified on this node (needs content with `field_gallery` / `field_category`)
- Sold badge not verified (`field_sold` = 0 on test node)
- Hero image layout not verified (no `field_main_image`)
- D7 side-by-side still outstanding (audit-level)

---

## 6. Verification commands

```bash
git status
find web/themes/custom/wcf_theme -type f | sort
cd web/themes/custom/wcf_theme && npm run build
ddev drush cr   # when runtime available
```

---

## Appendix: `find` inventory (theme source only)

Key paths under `web/themes/custom/wcf_theme`:

- `wcf_theme.info.yml`, `wcf_theme.libraries.yml`, `wcf_theme.theme`
- `package.json`, `src/scss/**`, `css/style.css`
- `templates/**`, `js/hero-slider.js`

(Full listing captured at audit time via `find web/themes/custom/wcf_theme -type f | sort`.)
