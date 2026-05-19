# Search Index Integrity Audit

**Date:** 2026-05-19  
**Branch:** `feature/search-index-integrity`  
**Index:** `stallion_content`  
**View:** `search_stallions` → `/search`

## Executive summary

Published stallions without `field_main_image` were excluded from `/search` because they were **removed from the Search API tracker** after a prior indexing failure. They were **not** excluded by Views filters, Twig rendering, or Search API field/processor rules tied to images.

Image absence does **not** prevent indexing when items are tracked and loaded correctly.

## Affected entity IDs

Published stallions missing from public `/search` (pre-remediation):

| nid | Title (sample) | `field_main_image` |
|-----|----------------|--------------------|
| 14 | 2013 frame overo ASB filly | empty |
| 16 | 2013 palomino ASB filly | empty |
| 17 | Hagia Sophia (legacy alias) | empty |
| 18 | Greasy Heel 2 | empty |
| 21 | *(published, no image)* | empty |
| 24 | *(published, no image)* | empty |
| 25 | Profile in Style | empty |
| 27 | *(published, no image)* | empty |
| 28 | Thomas the Tank | empty |
| 31 | Compliant Container Homes | empty |

**Correlation:** All 10 are published stallions with empty `field_main_image`. This is correlation with an earlier tracker drop, not a causal image filter.

## Exact root cause

### Mechanism

1. Search API index option `delete_on_fail: true` (pre-fix) called `trackItemsDeleted()` when `loadItemsMultiple()` could not load an item during batch indexing.
2. `search-api:reset-tracker` / `reindex()` only runs `trackAllItemsUpdated()` on **existing** `search_api_item` rows. It does **not** re-insert datasource items removed from the tracker.
3. Subsequent `search-api:index` reported success for the remaining tracker count (e.g. 267) while **10 items were permanently untracked**.
4. Backend table `search_api_db_stallion_content` contained 211 published stallion results = 221 published − 10 orphaned.

### What did **not** cause exclusion

| Area | Finding |
|------|---------|
| Index fields | No image/media field on `stallion_content` index |
| Processors | `entity_status`, `content_access`, `html_filter`, etc. — standard; no image gate |
| Views `/search` | No image filter; row plugin uses `stallion: card` |
| Twig `stallion-card` | `{% if image %}` — graceful omission only |
| Datasource `getItemIds()` | Includes all 10 nids (`14:en` … `31:en`) when tracker rebuilt |

### Evidence commands

```bash
# Tracker vs datasource (pre-rebuild): 267 tracked, 278 expected
ddev drush php:script scripts/wcf-search-integrity.php

# Manual index of imageless item succeeds when loaded
ddev drush php:eval '$i=\Drupal\search_api\Entity\Index::load("stallion_content"); $l=$i->loadItemsMultiple(["entity:node/14:en"]); echo count($i->indexSpecificItems($l));'

# Public query after orphaning
ddev drush php:eval '$i=\Drupal\search_api\Entity\Index::load("stallion_content"); $q=$i->query(); $q->addCondition("type","stallion"); echo $q->execute()->getResultCount();'
```

## Why `/stallions` works but `/search` fails

| Route | Engine | Image required? |
|-------|--------|-----------------|
| `/stallions` | SQL View `stallions` on `node_field_data` | No — lists all published stallions |
| `/search` | Search API index `stallion_content` | No — but **only indexed tracker items** appear |

## Safest remediation path

1. **Config:** Set `delete_on_fail: false` on `stallion_content` so transient load failures do not permanently drop tracker rows.
2. **Operational:** Run `scripts/wcf-search-integrity.php -- --apply --reindex` after migration or `search-api:clear` — rebuilds tracker from datasource when drift detected, then indexes.
3. **Do not use** `search-api:reset-tracker` alone after tracker loss — use `search-api:rebuild-tracker` or the integrity script.
4. **Theme:** `stallion-card--no-media` modifier for consistent card layout without placeholder media.

## Post-fix validation

After remediation:

- `expected_items` = `tracked_items` = 278 (or current datasource total)
- Public stallion query count = 221 (published)
- Imageless nids return results on `/search`
