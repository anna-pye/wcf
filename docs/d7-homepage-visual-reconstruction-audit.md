# D7 Homepage Visual Reconstruction Audit

**Date:** 2026-05-20  
**Branch:** `feature/style-parity-assets-hardening`  
**Slice:** 5 — D7 homepage chrome and visual layout reconstruction

---

## Step 1 — Safety check

| Check | Result |
|-------|--------|
| Branch | `feature/style-parity-assets-hardening` |
| `git status --short` | Clean (no unexpected module/migration/JS changes) |

---

## Step 2 — D7 visual structure findings

### Templates

| File | Role |
|------|------|
| `sites/all/themes/wcf/page.tpl.php` | Inner pages: `grey_bg` header band (logo, phone, Facebook), fixed right slide-out nav (`left_panel_holder` / `nav_bg`), content, 4-column footer |
| `sites/all/themes/wcf/page--front.tpl.php` | Front: full-width ResponsiveSlides hero in `banner` region, `banner_bg` overlay (logo, Facebook icon, phone), same right nav, SQL-driven product grids, `navy_blue_bg` CTA band, footer |

### Key assets (legacy — not copied to D11)

| Asset | Path | Use |
|-------|------|-----|
| Logo | `images/logo.png` (163×90) | Header + footer |
| Hero overlay | `images/white_bg.png` | Left panel scrim on front hero |
| Nav UI | `images/nav_arrow.png`, `close.png`, `dropdown_arrow1.png` | Slide menu |
| Footer bullet | `images/foo-bullet.png` | Footer link bullets |
| Container promo | `images/Home1.jpg`–`Home3.jpg` | Hardcoded homepage grid |

### Menu structure (hardcoded in D7 templates)

- Top-level: Home, Stallions (ASB / Stallions children), For Sale, Showcase, Foals, Agistment, Broodmares, Sold, Sold Stallions, Nomination, About, Contact, Compliant Container Homes
- Footer: 3 link columns + right column (email, logo, copyright)

### Homepage sections (front template)

1. Hero slider (`#slider1` / `banner` region) with left overlay panel
2. `content_top` region
3. **Stallions** — `.stallions h2` + `.fancybox_container` / `.fancy_box` / `.product_image_holdar` (3-col grid, image + title only)
4. **For sale** — same pattern
5. **Compliant Container Homes** — same pattern (static images)
6. **In-utero CTA** — `.navy_blue_bg` / `.section_2` (centered call copy + phone)

### Listing/card markup (D7)

```html
<div class="fancy_box gal">
  <motion class="product_image_holdar">
    <div class="product_image_holdar2"><a><img></a></motion>
  </div>
  <p class="text_align1">Title</p>
</div>
```

- No card border/shadow; ~230px image height; title below image, left-aligned bold text
- Section headings: `.stallions h2` — Bebas 50px, `#242424`, bottom border `#bbbbbb`, `padding: 1cm`

### Key CSS selectors (`custom.css`)

| Selector | Purpose |
|----------|---------|
| `.banner_main`, `.banner_bg`, `.logo`, `.address`, `.facebook` | Front hero + left overlay |
| `.left_panel_holder`, `.nav_bg`, `.navigation`, `.motion` | Fixed right nav (183px) |
| `.stallions h2`, `.fancy_box`, `.product_image_holdar` | Homepage listing sections |
| `.navigation-footer`, `.footer_heading`, `.foo-right-content` | Footer columns |
| `.grey_bg`, `.banner_main` (inner) | Non-front header band |
| `.navy_blue_bg`, `.section_2` | Homepage CTA band |

### D7 colours (reference only)

- Brand band: `#2eb361`
- Accent: `#6fbcac`
- Section headings: `#242424`
- Card titles: `#212121` bold Open Sans

---

## Step 3 — D11 current structure

| Area | Current state | Gap vs D7 |
|------|---------------|-----------|
| `page.html.twig` | Stacked header (branding + horizontal menu), bordered footer | No left panel / right drawer nav; inner pages lack grey band + contact column |
| `page--front.html.twig` | Same header above content (not overlaying hero) | Hero not full-bleed chrome; no left identity panel on hero |
| Homepage node | `field_hero_slides` + `field_feature_sections` (paragraphs) | Structure OK; visuals generic |
| Cards | `.stallion-card` with border, shadow, 4:3 media | Reads as generic Drupal card, not D7 image+title row |
| Footer | Single `system_menu_block:footer` | No multi-column layout, contact, or footer logo |
| Branding | `use_site_logo: false`, site name **WCF** | Logo deferred per assets hardening doc |

---

## Step 4 — Reconstruction plan (Slice 5)

| # | Deliverable | Approach |
|---|-------------|----------|
| 1 | Public header/chrome | Twig: `site-chrome` wrapper; SCSS: inner grey band + front absolute overlay; `<details>` mobile nav (no new JS) |
| 2 | Homepage hero | SCSS: taller hero, stronger overlay, centered call text on front; keep paragraph/field-driven content |
| 3 | Listing sections | SCSS: remove card chrome in homepage context; increase whitespace; display headings already in featured components |
| 4 | Footer | Twig: `site-footer__inner` grid; SCSS: multi-column menu + meta (mailto from `system.site` via preprocess); text branding only |
| 5 | Mobile | Collapsible nav via `<details>`; stacked footer; no horizontal overflow |

**Out of scope / deferred**

- Approved `logo.png` / `logo.svg`
- `white_bg.png`, `foo-bullet.png`, listing texture backgrounds
- Licensed Bebas/Proxima `@font-face` (using condensed system stack)
- D7 SQL homepage grids (content is paragraph + Views-driven)
- Hardcoded phone numbers (not in D11 homepage fields; contact via site mail + content)

---

## Step 5–8 — Implementation decisions

| Area | Decision |
|------|----------|
| Header (inner) | Grey band (`$color-d7-inner-band` / D7 `.grey_bg`) with branding left; `<details>` mobile menu; vertical nav styling on desktop |
| Header (front) | Absolute overlay; left identity panel via CSS gradient (no `white_bg.png`); fixed right nav panel on `md+` |
| Hero | Taller min-heights; stronger multi-stop overlay; front page centered call text; right padding reserves space for nav panel |
| Listings | Homepage context strips card border/shadow; image + title only; larger section gaps; removed listing “band” box on featured sections |
| Footer | Grid: footer menu (multi-column CSS grid) + meta column with `system.site` mail, site name, dynamic copyright year |
| Logo | Text branding only (`use_site_logo: false` unchanged); no `logo.svg` references |
| Phone | Not hardcoded; hero/contact copy remains field-driven |

---

## Files changed

| File | Change |
|------|--------|
| `web/themes/custom/wcf_theme/templates/layout/page.html.twig` | `site-chrome` + footer grid/meta |
| `web/themes/custom/wcf_theme/templates/layout/page--front.html.twig` | Front overlay chrome + footer |
| `web/themes/custom/wcf_theme/wcf_theme.theme` | `site_mail` in `preprocess_page` |
| `web/themes/custom/wcf_theme/src/scss/layout/_chrome.scss` | **New** — chrome + footer layout |
| `web/themes/custom/wcf_theme/src/scss/layout/_page.scss` | Simplified header/footer shells |
| `web/themes/custom/wcf_theme/src/scss/abstracts/_variables.scss` | Inner band colour, spacing, hero heights, `breakpoint-sm` |
| `web/themes/custom/wcf_theme/src/scss/style.scss` | Import `layout/chrome` |
| `web/themes/custom/wcf_theme/src/scss/components/_hero-slide.scss` | Overlay + front centered hero |
| `web/themes/custom/wcf_theme/src/scss/components/_featured-stallions.scss` | Remove listing band box |
| `web/themes/custom/wcf_theme/src/scss/components/_featured-content.scss` | Section spacing |
| `web/themes/custom/wcf_theme/src/scss/pages/_front.scss` | Homepage listing parity overrides |
| `web/themes/custom/wcf_theme/css/style.css` | Rebuilt (compressed) |

**Not changed:** node/card Twig, modules, migrations, JS libraries, config (except prior branding slice).

---

## Assets used or deferred

| Asset | Status |
|-------|--------|
| `logo.png` / `logo.svg` | **Deferred** — text site name only |
| `white_bg.png` | **Deferred** — CSS gradient panel |
| `foo-bullet.png` | **Deferred** — CSS circle bullet |
| `nav_arrow.png`, `close.png` | **Deferred** — text “Menu” toggle |
| Listing texture / webfonts | **Deferred** (per style system plan) |

---

## Commands run

```bash
git status --short && git branch --show-current
cd web/themes/custom/wcf_theme && npm run build
ddev drush cr
curl -sI https://wcf11.ddev.site/
curl -s https://wcf11.ddev.site/ | grep site-chrome
```

---

## QA

| Check | Result |
|-------|--------|
| `npm run build` | Success (exit 0) |
| `ddev drush cr` | Success |
| `/` HTTP 200 | Pass |
| `/stallions` HTTP 200 | Pass |
| Homepage markup | `layout-container--front`, `site-chrome`, `site-footer__inner` present |
| Inner page markup | `site-chrome--inner` on `/stallions` |
| `logo.svg` in theme HTML | Not referenced |
| Direct `logo.svg` request | Still 404 (unused) |
| Visual breakpoints 375 / 768 / 1200 | **Manual browser pass recommended** (not automated in this slice) |

**Manual follow-up:** confirm admin toolbar not overlapped by fixed front nav; verify keyboard use of `<details>` menu; confirm hero CTA contrast on live hero images.

---

## Remaining blockers

1. Approved logo asset for header/footer image parity  
2. Stakeholder choice on canonical brand green (`$color-primary` vs D7 greens)  
3. Licensed webfonts (Bebas, Open Sans, Proxima)  
4. Reference screenshots in repo for pixel sign-off  
5. Footer multi-menu columns would need additional menu blocks or a structured footer paragraph — single flat footer menu cannot replicate three D7 columns without config work  

---

## Recommended next slice

**Slice 6:** Implemented — see [d7-inner-page-parity-audit.md](d7-inner-page-parity-audit.md).

**Slice 7:** Expand footer/main menu config to approved D7 destinations; listing texture band when asset approved; container home publish + visual sign-off.
