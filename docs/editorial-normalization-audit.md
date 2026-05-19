# Editorial Normalization Audit

**Date:** 2026-05-19  
**Branch:** `feature/editorial-normalization`  
**Environment:** DDEV `wcf11.ddev.site`  
**Scope:** Migrated `stallion` nodes (`wcf_d7_node_stallion`, 276 imported)

## Summary counts

| Metric | Count |
|--------|------:|
| Total stallion nodes | 276 |
| Published | 221 |
| Unpublished (legacy `status=0`) | 55 |
| `field_status` = active | 147 |
| `field_status` = sold | 117 |
| `field_status` empty | 12 |
| `field_featured` = 1 (published) | 0 |

Audit method: `ddev drush php:script scripts/editorial-audit.php` (2026-05-19).

## Findings

### Empty summaries (276 — all stallions)

Every migrated stallion has an empty `body` summary. Metatag defaults use `[node:summary]` for `description`, Open Graph, and Twitter cards (`metatag.metatag_defaults.node__stallion.yml`).

**Affected:** All nids (14–289 range, full list in script output).

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Editors write 120–160 character summaries for published listings (priority: sold/active inventory on `/stallions`). |
| **Safe automated** | None without editorial copy source. D7 `meta_description` was not mapped in migration (deferred in migration audit). |

### Missing SEO descriptions on published (221)

All 221 published stallions lack both body summary and per-node metatag overrides → empty meta description at render time.

**Affected nids:** Same as published set (221).

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Populate body summary or Metatag description field per stallion. |
| **Safe automated** | Optional batch: trim first 155 characters from body **only where** body is non-empty and editors approve — **not recommended** without review (HTML stripping, legal copy). |

### Missing main images (11)

| nid | Title (abbrev.) |
|-----|-----------------|
| 14 | 2013 frame overo ASB filly |
| 16 | 2013 palomino ASB filly |
| 17 | 2013 dominant white & frame overo ASB filly |
| 18 | sold |
| 21 | 2012 Palomino ASB colt |
| 24 | 2013 True Black & white frame overo ASB filly |
| 25 | 2013 chestnut frame overo ASB filly |
| 27 | 2013 Buttermilk buckskin ASB fillyy |
| 28 | 2013 chestnut frame overo ASB filly |
| 31 | 2012 Bay Roan frame-overo ASB Thoroughbred filly |
| 37 | In foal to Glacial Gold (USA) |

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Upload/replace from legacy archive or retire listing. |
| **Safe automated** | None (no inventing media). |

### Broken aliases (0)

Pathauto resolves all stallions; no node returned system path `/node/{nid}` only.

**Note:** 8 published stallions retain **legacy short aliases** in addition to `/stallions/…` aliases (duplicate `path_alias` rows). Not broken, but SEO/canonical risk — see SEO validation doc.

### Duplicate titles (26 groups, 52 nodes involved)

Titles normalized case-insensitively. Representative groups:

| Normalized title | nids |
|------------------|------|
| 2013 bay dominant white asb filly | 15, 192 |
| 2013 dominant white & frame overo asb filly | 17, 193 |
| 2013 true black & white frame overo asb filly | 24, 106 |
| 2013 chestnut frame overo asb filly | 25, 28 |
| in foal to glacial gold (usa) | 37, 49 |
| in foal to profile in style (usa) | 44, 45, 46 |
| 2011 bay frame overo asb colt | 52, 91 |
| 2010 asb black thoroughbred colt | 55, 98 |
| 2002 asb mare | 64, 204 |
| 2009 asb chestnut thoroughbred filly | 81, 82 |
| 2010 chestnut asb filly | 95, 143 |
| 2009 dark bay thoroughbred colt | 101, 108 |
| 2009 anglo arabian filly - 75% thoroughbred, 25% arabian | 103, 105 |
| 2007 bay asb mare | 135, 239 |
| 2003 chestnut asb mare | 144, 201 |
| *(11 additional groups — run audit script for full list)* | |

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Disambiguate titles (year, sire, client prefix) per legacy editorial practice. |
| **Safe automated** | None. |

### Unpublished legacy items (55)

Expected: migration maps D7 `status=0` → `status=0`, `moderation_state=draft`. No published/unpublished leakage (`moderation_mismatch` = 0).

**Unpublished nids:** 55 nodes — filter in admin: `/admin/content?type=stallion&status=0` or Content health overview.

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Archive or publish after editorial review. |
| **Safe automated** | None. |

### Empty body (2)

| nid | Title |
|-----|-------|
| 37 | In foal to Glacial Gold (USA) |
| 231 | *(see admin)* |

### Placeholder-pattern body/summary (5)

Matched patterns: `placeholder`, `todo`, `tbd`, `coming soon`, `lorem ipsum`.

| nid | Title |
|-----|-------|
| 51 | UNDER OFFER & In foal to MOONLARK (USA) |
| 105 | 2009 Anglo Arabian Filly - 75% Thoroughbred, 25% Arabian |
| 201 | 2003 chestnut ASB mare |
| 218 | 2001 G3 Winning ASB TB mare |
| 240 | 2017 ASB TB gelding |

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Review body HTML; replace or unpublish. |
| **Safe automated** | None. |

### Inconsistent `field_status` (12 empty)

| nid | Title |
|-----|-------|
| 14, 16, 17, 18, 21, 24, 25, 27, 28, 31, 33, 37 | *(see SQL export above)* |

Overlaps heavily with missing-image set. Legacy `sold` flag may not have mapped when source ambiguous.

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Set active/sold to match business state. |
| **Safe automated** | Re-derive from D7 `wcf_product.sold` for nids with verifiable source rows only (migration replay) — **manual QA required**. |

### Invalid featured state (0)

No unpublished nodes flagged `field_featured=1`.

### Featured field unused (0 featured published)

Homepage featured stallions use Views/paragraph configuration, not `field_featured` on nodes. Editorial team should not assume the field drives homepage placement.

### Image quality (see media governance)

265 of 265 imaged stallions use **350px-wide** assets (legacy thumb fallback). Editorial replacement workflow required — not a migration defect to auto-fix.

### Search index gap (10 published, not in tracker)

Published stallions **missing** from `search_api_item` (not discoverable on `/search`):

`14, 16, 17, 18, 21, 24, 25, 27, 28, 31`

**Root cause (evidence):** Same 10 nids as published stallions without `field_main_image`. Reindex after `reset-tracker` does not add them (`delete_on_fail` removes failed items).

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Add main images, then reindex. |
| **Engineering** | Investigate why Search API skips imageless stallions; ensure listings can index without image if business requires. |

## Safe automated fixes (approved for ops)

1. Search API full reindex (see above).
2. Cache rebuild after editorial bulk updates: `ddev drush cr`.

## Manual-only fixes

- Body summaries and SEO descriptions
- Title disambiguation
- Main image replacement
- Legacy alias consolidation (`/stallions/…` as canonical)
- Placeholder body cleanup
- `field_status` correction
- Unpublished archive decisions

## Re-run audit

```bash
ddev drush php:script scripts/editorial-audit.php | jq '.issue_counts'
```
