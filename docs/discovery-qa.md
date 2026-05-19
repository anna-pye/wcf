# Discovery QA (Post-Migration)

**Date:** 2026-05-19  
**Branch:** `feature/editorial-normalization`  
**Environment:** DDEV `wcf11.ddev.site`  
**Data:** 276 stallions migrated; 221 published

## Route validation

| Route | HTTP | Notes |
|-------|------|-------|
| `/` | 200 | Homepage renders |
| `/stallions` | 200 | SQL View listing |
| `/search` | 200 | Search API + facets |
| `/stallions/pepper` | 200 | Sample migrated alias |
| `/stallions/secret-scribble` | 200 | Sample |
| `/hagia-sophia` | 200 | Legacy short alias (nid 17) still resolves |

Method: Drupal HTTP kernel subrequest (same as front controller).

## Search API integrity

```
ddev drush search-api:status
→ stallion_content: 100% complete, 267 indexed, 267 total
```

| Query | Public result count |
|-------|--------------------:|
| All indexable types | 211 |
| `type=stallion` only | 211 |
| Keywords `thoroughbred` | 53 |

**Gap:** 221 published stallions − 211 in public search = **10 published stallions not indexed**

Missing tracker IDs: `14, 16, 17, 18, 21, 24, 25, 27, 28, 31`

**Correlation:** These 10 are exactly the published stallions **without `field_main_image`** (see editorial audit). Full reindex was run — they remain absent from `search_api_item` (`delete_on_fail: true` on index).

**Remediation (manual + engineering):**

1. Add main images (editorial), then reindex; **or**
2. Investigate Search API indexing failure for imageless stallions (processor/config) — **not fixed in this phase**

Processors confirmed in config: `entity_status`, `content_access` — unpublished excluded (55 draft stallions not in public results).

## Facet counts (`/search`, unfiltered)

Observed at audit time (published indexable content):

| Facet | Values | Notes |
|-------|--------|-------|
| Content type | Stallion (majority), Container home (if any published) | Counts align with ~211 results |
| Stallion status | `active`, `sold` | Matches `field_status` distribution |
| Categories | Hidden (`facet-hidden`) | 0 categorized published items |

**Filtered test:** `?f[0]=content_type:stallion` — active filter state, facets summary “Clear all filters” works (per prior `docs/search-validation.md` pattern).

## Card and teaser rendering

| Context | View mode | Component |
|---------|-----------|-----------|
| `/stallions` | `card` | `stallion-card` Twig + SCSS |
| `/search` stallion hits | `card` | Same |
| `/search` container_home hits | `teaser` | Default node teaser — **no card component** |
| Homepage featured stallions | View block → `card` | `featured-stallions` section |

**Finding:** Container homes on `/search` lack image in teaser display (`field_main_image` hidden in `node.container_home.teaser` config) — functional but visually inconsistent with stallion cards.

## Responsive images

- Stallion cards: `responsive_image.styles.card` via media `card` view mode — **PASS**
- Main images are 350px source assets — display upscales in 4:3 box (soft blur risk) — editorial issue

## Missing image fallbacks

- Theme shows broken/empty media handling via empty `field_main_image` — 11 stallions with no image (see editorial audit)
- No fake placeholder images generated — **PASS** (governance)

## Moderation exclusion

- 55 unpublished stallions: not in public search
- `moderation_mismatch`: 0
- Container home drafts: excluded from public search when unpublished

## Homepage featured sections

- Featured stallions paragraph + View block render 200
- `field_featured` on nodes: **0** published nodes set — homepage curation is View-driven, not field-driven
- Re-validate after editors set featured flags **if** business adopts field (optional)

## Manual spot-check checklist

- [ ] `/` — hero + featured sections load
- [ ] `/stallions` — pagination, status filter, card grid
- [ ] `/search` — keyword + facet + reset
- [ ] 5 random `/stallions/{alias}` pages — images, sold badge, phone CTA
- [ ] Confirm 10 missing nids appear after reindex

## Commands

```bash
ddev drush search-api:status
ddev drush php:eval "\$i=\Drupal\search_api\Entity\Index::load('stallion_content'); echo \$i->query()->execute()->getResultCount();"
```
