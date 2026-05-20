# Style parity assets hardening

**Date:** 2026-05-20  
**Branch:** `feature/style-parity-assets-hardening`  
**Base:** `main` after merge of PR #10 (`feature/d7-d11-style-parity-audit`)

## Scope (this slice)

Resolve the **logo / branding 404** without copying unapproved legacy assets. Document remaining blockers; do **not** change fonts, listing texture, canonical brand green, or migration/module/JS scope in this slice.

---

## Audit: merged theme state (main)

| Check | Result |
|-------|--------|
| Style parity PR on `main` | Yes — `d5b57f91` merge |
| `web/themes/custom/wcf_theme/logo.svg` | **Absent** (no `logo*` files in theme) |
| `config/sync/system.theme.global.yml` `logo.path` | Empty; `use_default: true` |
| `block.block.wcf_theme_site_branding` | Region `header`; `use_site_logo: true` (pre-fix) |
| Compiled `css/style.css` `url()` refs | None |
| `@font-face` / WOFF2 in theme | None |

### Runtime verification (DDEV `https://wcf11.ddev.site`)

```bash
curl -sI https://wcf11.ddev.site/themes/custom/wcf_theme/logo.svg
# HTTP/2 404
```

Homepage HTML (pre-fix) rendered:

```html
<img src="/themes/custom/wcf_theme/logo.svg" alt="Home" fetchpriority="high" />
```

Drupal’s `system_branding_block` falls back to `{active_theme}/logo.svg` when `use_site_logo` is enabled and no site logo is configured in `system.theme` — hence the 404 despite no asset in git.

### D7 reference (not copied)

| Asset | Legacy path | Status |
|-------|-------------|--------|
| Header/footer logo | `~/drupal7-legacy/sites/all/themes/wcf/images/logo.png` (163×90 PNG, 2014) | Exists in legacy only |
| D11 approved equivalent | — | **None in repo** |

Per project rules: **do not copy** `logo.png` until explicitly approved and added through the agreed asset pipeline (theme file or `system.theme` logo upload + config export).

---

## Decision: branding without logo (implemented)

| Option | Chosen? | Rationale |
|--------|---------|-----------|
| Add `logo.svg` from D7 `logo.png` | No | Unapproved asset; violates non-negotiables |
| Upload logo via admin only | Deferred | No approved file to upload |
| **Site name text only** | **Yes** | Removes broken `<img>`; preserves header structure and existing `.site-branding__name` SCSS |

### Config change

`config/sync/block.block.wcf_theme_site_branding.yml`:

- `use_site_logo: false`
- `use_site_name: true` (unchanged)
- `use_site_slogan: false` (unchanged)

Site title comes from `system.site:name` (**WCF**). Full marketing name can be set later via config when editorial approves; out of scope for this slice.

### Post-fix verification

After `ddev drush cim -y` and `ddev drush cr`:

- `GET /themes/custom/wcf_theme/logo.svg` may still 404 if requested directly — **no longer linked** from branding block
- Homepage should show site name link only (no `logo.svg` in markup)

---

## Deferred blockers (stop — awaiting assets/decisions)

Do **not** implement until inputs exist:

| Blocker | Required input | Notes |
|---------|----------------|-------|
| Canonical brand green | Stakeholder decision | D11 token `$color-primary: #2d5016` vs D7 bright greens in `custom.css` — see `docs/d7-d11-style-parity-audit.md` |
| Licensed webfonts | Confirmed WOFF2 files + license | D7 used Open Sans, Bebas Neue, Proxima Nova under `fonts/` — dormant `_fonts.scss` pattern only after files approved |
| Listing texture | Approved `listing_texture_bg.jpg` (or successor) | D7 `images/` texture; no `url()` in D11 CSS today |
| D7 vs D11 screenshots | Captures at 375 / 768 / 1200 | Not in repo; needed for pixel parity sign-off |
| Approved logo | `logo.svg` or PNG + export strategy | Re-enable `use_site_logo` and/or set `system.theme.global` logo path |

---

## Non-negotiables compliance (this slice)

- No redesign
- No unapproved asset copy
- No font activation
- No migration / custom module / JS changes
- No D7 plugin port
- SCSS/CSS unchanged — `css/style.css` rebuild not required

---

## Commands

```bash
git checkout main && git pull
git checkout feature/style-parity-assets-hardening
ddev drush cim -y && ddev drush cr
curl -sI https://wcf11.ddev.site/themes/custom/wcf_theme/logo.svg
curl -s https://wcf11.ddev.site/ | grep -i 'site-branding\|logo\.svg'
```
