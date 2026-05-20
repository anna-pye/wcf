# D7 → D11 Style System Plan

**Date:** 2026-05-20  
**Branch:** `feature/d7-d11-style-parity-audit`  
**Companion:** [d7-d11-style-parity-audit.md](./d7-d11-style-parity-audit.md)

This plan defines how Winning Colours Farm D11 theming should **evolve toward** D7 visual parity using maintainable tokens and components — without porting `custom.css` or jQuery plugins wholesale.

---

## Principles

1. **Tokens first** — colors, type, spacing in `src/scss/abstracts/`; components consume tokens only.
2. **One card system** — `.stallion-card` for all discovery cards (already shared).
3. **One button system** — `.button`, modifiers in `components/_buttons.scss`.
4. **BEM + Twig components** — markup in `templates/components/`, not field-level hacks.
5. **Config-driven content** — no hardcoded D7 SQL blocks or menu URLs in Twig.
6. **Accessibility non-negotiable** — focus rings, contrast, reduced motion (already in hero slider).

---

## Colour token plan

### D7 reference palette (source: `~/drupal7-legacy/.../wcf/css/custom.css`)

| Token name | Value | D7 usage |
|------------|-------|----------|
| `$color-d7-brand` | `#2eb361` | Top banner `.wraper` |
| `$color-d7-accent` | `#6fbcac` | Address, section highlights |
| `$color-d7-cta` | `#2bb99b` | Form submit, positive actions |
| `$color-d7-cta-text` | `#015c49` | Text on teal buttons |
| `$color-d7-red` | `#e8463a` | Vegas/promo accents |
| `$color-d7-title-muted` | `#8c8c8c` | Listing card titles |
| `$color-d7-body-muted` | `#9d9d9d` | Card excerpts |
| `$color-d7-border-teal` | `#7de1cb` | Outline buttons (read more) |
| `$color-d7-heading` | `#242424` | Section headings |
| `$color-d7-black` | `#000000` | Card borders (1px) |

### D11 semantic mapping (target state)

| Semantic token | Initial value (slice 0) | Target (post sign-off) |
|----------------|-------------------------|-------------------------|
| `$color-text` | `#1a1a1a` | `#242424` |
| `$color-text-muted` | derived | `$color-d7-body-muted` |
| `$color-primary` | `#2d5016` (current) | `$color-d7-cta` or `$color-d7-brand` |
| `$color-primary-hover` | `#1f3810` | darken `$color-primary` 8% |
| `$color-accent` | — | `$color-d7-accent` |
| `$color-danger` | `#8b2635` (sold badge) | keep or align to `$color-d7-red` |
| `$color-text-inverse` | `#ffffff` | unchanged |
| `$color-overlay` | `rgba(0,0,0,0.45)` | tune after hero screenshot compare |

**Slice 0 (this branch):** D7 values stored as `$color-d7-*` references in `_variables.scss`; active UI still uses `$color-primary` until stakeholder confirms which green is canonical (D7 used multiple greens).

---

## Typography plan

### D7 stacks

| Role | D7 font family | Approx use |
|------|----------------|------------|
| Display / section | `bebas_neueregular` | `.stallions h2`, `.about_container h6` |
| UI / body | `open_sansregular` | Body, cards |
| Emphasis | `open_sansbold` | Spans, CTAs |
| Footer headings | `proxima_novasemibold` | `.footer_heading` |
| Hero alternate | `helveticaneue` | `.banner_new` |

### D11 approach

| Phase | Action |
|-------|--------|
| Slice 0 | System stack in `$font-family-base` (performance, no FOUT) |
| Slice 1 | Add `@font-face` in `abstracts/_fonts.scss` after confirming font files licensed for self-hosting; copy from D7 `fonts/` or use subset woff2 |
| Slice 1 | `$font-family-display` for headings ≥ h2 in listing/homepage sections |
| Slice 2 | Size scale using `clamp()` matching D7 hierarchy (Bebas ~40–50px, body 18px) |

**Do not** link Google Fonts without privacy review; prefer local WOFF2 in theme.

---

## Spacing scale

Existing scale (keep):

| Token | Value |
|-------|-------|
| `$spacing-xs` | 0.5rem |
| `$spacing-sm` | 1rem |
| `$spacing-md` | 1.5rem |
| `$spacing-lg` | 2.5rem |
| `$spacing-xl` | 4rem |

Add in slice 1 if needed:

- `$spacing-2xs: 0.25rem` — badge padding
- `$spacing-2xl: 6rem` — section bands matching D7 `padding:1cm` sections (convert to rem)

---

## Button system

### Classes

| Class | Use |
|-------|-----|
| `.button` | Base (cursor, font inherit) |
| `.button--primary` | Filled CTA — hero, nomination, feature cards, webform submit |
| `.button--outline` | D7 `.read_more` — teal border, uppercase (slice 1) |
| `.button--ghost` | On dark hero overlays (optional) |

### Rules

- Minimum touch target 44×44px
- `:focus-visible` outline 3px
- No `!important` (D7 used heavily — do not port)

Implemented in `components/_buttons.scss`; webform submit selectors remain in `_forms.scss` but should mirror button tokens.

---

## Card system

**Single component:** `templates/components/stallion-card.html.twig` + `components/_stallion-card.scss`.

| Element | Spec |
|---------|------|
| Media | 4:3 `aspect-ratio`, `object-fit: cover` |
| Border | 1px subtle (D7 used `#000` — soften to `rgba(0,0,0,0.12)` unless sign-off requires hard black) |
| Title | 1.125rem → consider `$color-d7-title-muted` in slice 1 |
| Status | Modifier `--sold`, `--active`, `--retired` |

Container homes: same component; optional modifier `.stallion-card--container-home` in slice 2 if sold badge styling diverges.

---

## Hero / image treatment

| Surface | D11 implementation |
|---------|-------------------|
| Homepage hero | Paragraph `hero_slide`, `.hero-slide`, `hero-slider` library |
| Stallion hero mode | `.stallion-hero` in `_stallion.scss` |
| D7 Flexslider/ResponsiveSlides | **Not ported** — CSS grid stack + JS slide toggle |

Image parity:

- Use responsive image styles from media view modes (`card`, `hero`) — already config-driven
- Gallery: CSS grid in `.stallion__gallery`; slice 2 add bordered thumb style from D7 `.image_holdar`

---

## Form styling

Target selectors (existing):

- `.webform-submission-wcf-contact-form`
- `.webform-submission-wcf-nomination-form`

Align inputs to:

- Border `rgba(0,0,0,0.2)`, radius 4px (D7 used 3px)
- Submit uses `.button--primary` tokens
- Required asterisk `#b00020` (WCAG)

---

## Mobile breakpoints

| Token | Value | Aligns with |
|-------|-------|-------------|
| `$breakpoint-md` | 48rem (768px) | Bootstrap 2 `@grid-float-breakpoint` neighborhood |
| `$breakpoint-lg` | 75rem (1200px) | D7 `col-lg-*` |

Use mobile-first `min-width` queries only (current pattern).

---

## Accessibility rules

1. **Color contrast** — when switching to D7 teal/green, verify 4.5:1 for body text and 3:1 for large display type.
2. **Focus** — all `.button`, menu links, slider controls: visible `:focus-visible`.
3. **Motion** — `prefers-reduced-motion: reduce` on hero slider (already present).
4. **Slides** — `aria-roledescription="slide"` on hero (already in Twig).
5. **Images** — alt text from media fields; decorative heroes may use empty alt when title is adjacent.

---

## What should intentionally differ from D7

| Area | Why |
|------|-----|
| jQuery 1.8 / Bootstrap 2 / Fancybox | Security, maintainability |
| Raw SQL on `page--front.tpl.php` | Replaced by Views + paragraphs |
| Multiple dated `custom_*.css` files | Single SCSS pipeline |
| Fixed `width:25em` floated cards | CSS Grid, responsive columns |
| Hardcoded menu URLs in `page.tpl.php` | Drupal menus + Pathauto |
| `!important` clearfix hacks | Modern layout |
| Font Awesome 3 in theme | Use SVG icons or FA6 subset if needed |
| IE7-specific stylesheets | Dropped |

---

## Build workflow (documented)

```bash
cd web/themes/custom/wcf_theme
npm install   # once
npm run build # after SCSS changes — commits css/style.css
npm run watch # local dev
```

Do **not** add Webpack/Gulp unless project standards change; `sass` CLI is sufficient.

---

## Implementation slices (recommended)

| Slice | Deliverables |
|-------|----------------|
| **0 (this branch)** | Audit, system plan, `_buttons.scss`, D7 token variables, rebuild CSS |
| **1** | Fonts, primary color activation, header/footer/menu, site branding block |
| **2** | `node--container-home--full`, gallery grid, listing texture optional background |
| **3** | Homepage section typography (Bebas headings), testimonial/feature bands |
| **4** | Search + facet UI polish, outline buttons on cards if required |

---

## Sign-off checklist before slice 1

- [ ] Side-by-side screenshots: D7 prod vs D11 staging (homepage, stallion, listing, footer)
- [ ] Confirm canonical brand green (`#2eb361` vs `#2bb99b` vs keep `#2d5016`)
- [ ] Confirm webfont licensing for self-hosting
- [ ] Place branding block in `header` region
