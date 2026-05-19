# Discovery Consistency Remediation

**Date:** 2026-05-19  
**Scope:** Card/teaser parity across discovery surfaces (no new Views or components)

## Surfaces audited

| Surface | Engine | Stallion view mode | Container home |
|---------|--------|-------------------|----------------|
| `/stallions` | View `stallions` | `card` → `stallion-card` | N/A |
| `/search` (stallion) | Search API View | `card` → `stallion-card` | `teaser` |
| `/search` (container) | Search API View | N/A | `teaser` (updated) |
| Homepage featured | View block | `card` | N/A |

## Findings

### Imageless stallions

- **Before:** Missing from `/search` (tracker orphan — see `docs/search-index-integrity-audit.md`).
- **Theme:** `stallion-card.html.twig` already omits media when empty — no broken `<img>`.
- **After:** `stallion-card--no-media` SCSS modifier for consistent card body min-height.

### Container home on `/search`

- **Before:** `field_main_image` hidden in `node.container_home.teaser` — text-only teasers vs stallion cards.
- **After:** Teaser display shows `field_main_image` using media `card` view mode (same responsive style as stallion cards).

### Summaries / excerpts

- All stallion summaries empty — cards and search excerpts weak until `scripts/remediate-empty-summaries.php` applied.

### Image quality

- 350px legacy thumb assets upscale in 4:3 card box — editorial replacement, not a code defect.

## Changes made (minimal)

1. `core.entity_view_display.node.container_home.teaser` — expose main image (`card` media view mode).
2. `stallion-card--no-media` — layout normalization only.
3. No duplicate SCSS components; no new view modes beyond teaser config.

## Not changed (by design)

- Container home does not use `stallion-card` Twig (different bundle semantics).
- No Layout Builder or new listing Views.
- No placeholder media entities.

## Validation checklist

- [ ] `/stallions` — grid, filters, imageless cards render
- [ ] `/search` — stallion + container_home rows visually aligned
- [ ] Homepage featured stallions block
- [ ] Imageless stallion appears in search results
