# Stallion nomination CTA

## Phase 1 — Audit (stallion full view)

### View display

- No `core.entity_view_display.node.stallion.full.yml` in `config/sync`.
- Canonical stallion pages use view mode `full`; Drupal falls back to `core.entity_view_display.node.stallion.default.yml` for field rendering.
- `field_cta_phone` is enabled (telephone_link, weight 6).

### Templates

| File | Role |
|------|------|
| `web/themes/custom/wcf_theme/templates/node/node--stallion--full.html.twig` | Stallion detail (full) layout |
| `web/themes/custom/wcf_theme/templates/node/node--stallion--hero.html.twig` | Hero band; phone only |
| `web/themes/custom/wcf_theme/wcf_theme.theme` | `hook_preprocess_node__stallion()` — status, featured, summary fallback |

### Existing CTA area (full template)

- `.stallion__cta` wraps `content.field_cta_phone` only.
- No nomination or webform link before this change.

### Theme CTA patterns (paragraphs, not stallions)

- `wcf_theme_build_paragraph_cta_link()` — paragraph `field_cta_url` / `field_cta_text` → link render array with `button button--primary`.
- Used by hero slides and feature cards; **not** reused for stallions (no paragraph on stallion node).

### Legacy (D7)

- `drupal7-legacy/.../product/templates/front/product_view.tpl.php` (~line 359): hardcoded `http://winningcoloursfarm.com.au/nomination` (“Application form”).

### D11 target

- Webform `wcf_nomination` at `/nomination` (`config/sync/webform.webform.wcf_nomination.yml`, `page_submit_path: /nomination`).
- Menus already use `internal:/nomination` (see `docs/legacy-menu-url-parity-implementation.md`).

### Gap

- Stallion full view had phone CTA only; no link to nomination workflow.

---

## Phase 2 — Implementation

### Approach

- Theme-level link render array from `wcf_theme_build_stallion_nomination_cta_link()` (mirrors paragraph CTA helper; no new route/form/config).
- Exposed in `wcf_theme_preprocess_node__stallion()` when `view_mode === 'full'`.
- Template: `node--stallion--full.html.twig` — nomination CTA beside existing `field_cta_phone`.

### CTA target

- Route: `entity.webform.canonical`, parameter `webform` = `wcf_nomination` (resolves to `/nomination`).
- Label: “Nominate this stallion”.

### Files changed

- `web/themes/custom/wcf_theme/wcf_theme.theme`
- `web/themes/custom/wcf_theme/templates/node/node--stallion--full.html.twig`
- `web/themes/custom/wcf_theme/src/scss/pages/_stallion.scss`
- `web/themes/custom/wcf_theme/css/style.css` (compiled)

### Accessibility

- Visible link text: “Nominate this stallion” (descriptive, not “click here”).
- Primary button classes for consistent focus/hover with other site CTAs.
- Phone CTA unchanged (`field_cta_phone` / telephone_link).

---

## Phase 4 — Validation

Commands (2026-05-20):

```bash
ddev drush cr
ddev drush cst
curl -s https://wcf11.ddev.site/stallions/clients-foal-tilly | grep -i "Nominate this stallion"
curl -sI https://wcf11.ddev.site/nomination | head -1
```

| Check | Result |
|-------|--------|
| `drush cst` | No differences between DB and sync directory |
| Stallion detail CTA | Present: `<a href="/nomination" class="stallion__nomination-cta button button--primary">Nominate this stallion</a>` on `/stallions/clients-foal-tilly` |
| `/nomination` | `HTTP/2 200` |

---

## Remaining gaps

- Hero view mode still shows phone only (intentional; detail CTA is full-only).
- Nomination form does not pre-fill stallion context from the detail page (future enhancement if required).
