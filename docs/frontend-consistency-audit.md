# Frontend Consistency Audit

**Date:** 2026-05-19  
**Branch:** `feature/editorial-normalization`  
**Scope:** Identify only — no redesign, no new components

## Surfaces compared

| Surface | Template / SCSS | Image ratio | Summary length |
|---------|-----------------|-------------|----------------|
| Stallion card (`/stallions`, `/search`) | `stallion-card` component, 4:3 media | 4:3 `aspect-ratio` | 120 chars (`card` view mode) |
| Container home search hit | Default node teaser | None (image hidden in display) | 600 chars trim |
| Homepage featured stallions | `featured-stallions` wrapper + same card grid | 4:3 via card | 120 chars |
| Homepage featured content (generic) | `featured-content` section | Varies by paragraph | Varies |

## Inconsistencies identified

### Image ratios

- **PASS** within stallion surfaces (card component enforces 4:3).
- **FAIL** cross-bundle: container homes on `/search` show text-only teasers — no visual parity with stallion cards.

### Spacing

- Stallion cards: `$spacing-sm` body padding, consistent border radius.
- Container teaser: core node markup — different vertical rhythm on `/search`.
- Featured sections: additional header spacing in `featured-stallions` — intentional section chrome.

### Teaser lengths

- Stallion: 120 characters.
- Container home: 600 characters — long excerpts break grid visual balance on mixed search results.

### CTA treatments

- Stallion card: status badge, title hover, phone on full node only.
- Container home teaser: “Read more” node link only — no status badge pattern.

## Safe minimal normalization (optional, not applied)

If approved in a future micro-pass **without new systems**:

1. **Config only:** Add `field_main_image` to `node.container_home.teaser` display using media `card` view mode — reuses existing responsive style.
2. **SCSS only:** Add `.node--view-mode-teaser` rules mirroring `.stallion-card__media` aspect ratio under existing `style.scss` imports — **do not** create new component files.

**Not recommended now:** This phase is documentation-only per operational maturity scope.

## Homepage vs listing

- Featured stallions block uses same card template as `/stallions` — **consistent**.
- Empty summaries hide card excerpt area (all stallions) — consistent but sparse.

## Validation

Manual compare in browser:

- `/stallions` (first row)
- `/search` (mixed types)
- `/` (featured stallions paragraph)

```bash
cd web/themes/custom/wcf_theme && npm run build
```
