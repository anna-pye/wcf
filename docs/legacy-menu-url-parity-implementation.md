# Legacy Menu and URL Parity Implementation

**Date:** 2026-05-20  
**Branch:** `feature/legacy-menu-url-parity-implementation`  
**Audits:** `docs/legacy-url-inventory.md`, `docs/legacy-menu-parity-audit.md`

## Summary

Implemented approved D11 navigation and legacy URL redirects. D7 hardcoded desktop nav/footer from `page.tpl.php` and `page--front.tpl.php` are **not** recreated in Twig. Canonical destinations use config-managed menus and the Redirect module.

## D7 hardcoded link coverage

| D7 link (header/footer) | D11 handling |
|-------------------------|--------------|
| Home | `standard.front_page` (main) + footer menu link |
| Stallions (`/category/stallions`) | Main/footer → `/stallions`; 301 from `/category/stallions` |
| ASB Stallions (`/category/asb_stallions`) | 301 → `/stallions` (underscore + hyphen paths) |
| For Sale, Foals, Broodmares, Sold categories | 301 → `/stallions` (not in menu per approved scope) |
| Showcase | 301 → `/` (not in menu) |
| Sold Stallions | 301 → `/stallions` (not in menu) |
| Nomination Form | Main/footer → `/nomination` |
| Contact Us | Main/footer → `/contact-us` |
| About Us, Agistment | Not in approved simplified menu (pages may exist; no menu link added) |
| Compliant Container Homes | No D11 listing route; individual nodes use `/container-homes/[title]` Pathauto only |
| Facebook (header/footer) | Footer menu external link |
| Product “Application form” absolute URL | **Gap** — see Stallion CTA below |

Commented D7 items (`news`, `faq`, `links`, show-mares, individual stallion shorts) were not migrated.

## Main menu (`system.menu.main`)

Placed via `block.block.wcf_theme_mainnavigation` in `primary_menu`.

| Title | Path | Source |
|-------|------|--------|
| Home | `/` | `standard.front_page` (core) |
| Stallions | `/stallions` | `menu_link_content` |
| Search | `/search` | `menu_link_content` |
| Nomination | `/nomination` | `menu_link_content` |
| Contact | `/contact-us` | `menu_link_content` |

**Not added:** `/category/*`, `/showcase`, `/sold-stallions`, Container Homes (no canonical listing route).

Views menu integration disabled in config (`views.view.stallions`, `views.view.search_stallions` — `menu.type: none`) to avoid duplicate links.

## Footer menu (`system.menu.footer`)

Placed via new `block.block.wcf_theme_footer` in `footer` region.

| Title | Path |
|-------|------|
| Home | `/` |
| Stallions | `/stallions` |
| Search | `/search` |
| Nomination | `/nomination` |
| Contact | `/contact-us` |
| Facebook | `https://www.facebook.com/pages/Winning-Colours-Farm/353221651451104` (new tab) |

## Redirects (301, Redirect module)

Created on local environment (content entities — not in `config/sync`). Replicate on other environments with:

```bash
ddev drush php:script scripts/legacy-menu-url-parity-setup.php -- --apply
```

| Source | Target |
|--------|--------|
| `/category/stallions` | `/stallions` |
| `/category/asb-stallions` | `/stallions` |
| `/category/asb_stallions` | `/stallions` |
| `/category/foals` | `/stallions` |
| `/category/broodmares` | `/stallions` |
| `/category/for-sale` | `/stallions` |
| `/category/sold` | `/stallions` |
| `/showcase` | `/` |
| `/sold-stallions` | `/stallions` |

**Alias conflicts:** None for the paths above (no active `path_alias` rows blocked redirect creation).

**Left unchanged:** Existing stallion short-alias redirects (`/vegas`, `/moonlark`, etc.) from `scripts/canonical-redirect-remediation.php`. The `compliant_container_homes` redirect to a stallion node was not modified.

## Stallion CTA (nomination)

- **D7:** `product_view.tpl.php:359` — absolute `http://winningcoloursfarm.com.au/nomination`
- **D11:** `node--stallion--full.html.twig` exposes `field_cta_phone` only; no nomination/application CTA in template or display config.
- **Action:** Documented gap. No new design section added without evidence.

## Theme check

- `page.html.twig` / `page--front.html.twig` render `page.primary_menu` and `page.footer` blocks only.
- Grep: no hardcoded `/category/`, `/showcase`, `/sold-stallions`, or absolute nomination URLs in `wcf_theme` or custom modules.

## Config exported (`config/sync`)

- `block.block.wcf_theme_footer.yml` (new)
- `views.view.stallions.yml` (disable Views-provided main menu link)
- `views.view.search_stallions.yml` (disable Views-provided main menu link)

`menu_link_content` and Redirect entities are **content** entities; apply via setup script after `drush cim` on new environments.

## Validation results (2026-05-20, wcf11.ddev.site)

| URL | Expected | Result |
|-----|----------|--------|
| `/` | 200 | 200 |
| `/stallions` | 200 | 200 |
| `/search` | 200 | 200 |
| `/contact-us` | 200 | 200 |
| `/nomination` | 200 | 200 |
| `/category/stallions` | 301 | 301 |
| `/category/foals` | 301 | 301 |
| `/category/sold` | 301 | 301 |
| `/showcase` | 301 | 301 |
| `/sold-stallions` | 301 | 301 |
| `ddev drush cst` | clean | clean |

```bash
grep -RIn "/category/\|/showcase\|/sold-stallions\|winningcoloursfarm.com.au/nomination" web/themes/custom/wcf_theme web/modules/custom
# (no matches)
```

## Remaining gaps

1. **Stallion detail nomination CTA** — no `/nomination` link on stallion full view; needs editorial/design decision.
2. **Container Homes** — no site-wide landing route; Pathauto per node only.
3. **About Us / Agistment** — D7 footer links not in approved simplified menu; add when pages are confirmed and menu scope expands.
4. **Menu/redirect deploy on new envs** — run `scripts/legacy-menu-url-parity-setup.php -- --apply` after config import.
5. **Foal year subpaths** (`/category/foals/{year}`) — deferred per classification audit.

## Untracked audit docs (pre-flight, not staged)

- `docs/legacy-custom-module-inventory.md`
- `docs/legacy-custom-module-parity-audit.md`
- `docs/legacy-functionality-gap-report.md`
- `docs/legacy-menu-parity-audit.md`
- `docs/legacy-url-inventory.md`
- `scripts/wcf-create-webforms.php`
