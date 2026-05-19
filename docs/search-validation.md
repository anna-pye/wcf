# Search Index Validation

**Date:** 2026-05-19  
**Environment:** DDEV (`wcf11.ddev.site`)  
**Index:** `stallion_content`  
**View:** `search_stallions` → `/search`

## Index status

```
ddev drush search-api:status
→ stallion_content: 100% complete, 4 indexed, 4 total
```

**Note:** “4 indexed” includes unpublished entities in tracker storage; public query count is 2 (see below).

## Content fixture evidence

| nid | type | Published | In index tracker | In public search |
|-----|------|-----------|------------------|------------------|
| 33 | container_home | Yes | Yes | Yes |
| 34 | container_home | No | Yes | No |
| 35 | container_home | No | Yes | No |
| 39 | stallion | Yes | Yes | Yes |

**Articles:** No article nodes in database at audit time—bundle indexed in config but zero fixtures to validate.

## Unpublished exclusion

**Processors:** `entity_status` (preprocess_index -10), `content_access` (preprocess_query -4) — `search_api.index.stallion_content.yml`

**Test:**

```php
$index->query()->execute()->getResultCount(); // → 2
```

Unpublished nodes 34, 35 remain in `search_api_item` but are **filtered at query time**. Expected Search API behavior.

**Verdict:** Unpublished content excluded from public results — **PASS**

## Moderation states

Workflow `editorial` applies to `article`, `homepage`, `stallion` (`workflows.workflow.editorial.yml`). Container homes use published/unpublished only (no moderation_state rows in DB for audited nodes).

| type | moderation_state in DB | Notes |
|------|------------------------|-------|
| stallion | NULL (published) | Published default revision visible |
| container_home | NULL | Draft nodes unpublished (`status=0`) |

**Verdict:** Moderation respected via publish state + `entity_status` for moderated bundles — **PASS** (limited fixtures; re-validate after D7 ingestion of draft/review content)

## Fulltext search

**Test:** `keywords=container` on `/search`

- Reset button appears when keywords present (`Reset search` input with class `site-search__reset`)
- Result set narrows to container_home matches

**Verdict:** Keyword filter — **PASS**

## Facet counts

Rendered `/search` (no filters):

| Facet | Value | Count |
|-------|-------|-------|
| Content type | Container Home | (1) |
| Content type | Stallion | (1) |
| Stallion status | active | (1) |
| Tags | — | hidden (empty) |

**Filtered test:** `?f[0]=content_type:stallion`

- Content type facet shows active state
- Facets summary shows “Clear all filters” → `/search`
- Result count updates to stallion-only

**Verdict:** Facet counts and active state — **PASS**

## Taxonomy filtering

- Tags vocabulary: 1 term (`sf`, tid 1)
- Tags facet hidden when empty (`facet-empty facet-hidden` on block)
- Stallions SQL view exposes tag filter with term `sf` on `/stallions`

**Verdict:** Taxonomy facet wiring correct; **no tagged published index items** to validate tag facet counts on `/search` — **INCONCLUSIVE** until migrated content carries tags

## Reset behavior

| Mechanism | Behavior | Status |
|-----------|----------|--------|
| Facets summary | “Clear all filters” link to `/search` | PASS |
| Exposed form | `Reset search` when keywords param present | PASS |
| Empty state | Link to `/search` in empty message | PASS (config) |

## Sort order

Default sorts: `featured` DESC, `created` DESC (`views.view.search_stallions.yml`). Featured stallion appears before container home in unfiltered results (observed).

## Render modes in results

- Stallion → `card` view mode (consistent card component)
- Container home → `teaser` view mode (generic markup, no card template)

**Finding:** Functional but visually inconsistent. Not a search-index defect—display config. Flag for editorial/migration polish.

## Post-migration re-validation checklist

After controlled D7 ingestion:

1. `drush search-api:reset-tracker stallion_content && drush search-api:index stallion_content`
2. Confirm draft/review nodes excluded from `/search`
3. Re-test tag facet with tagged published stallions/articles
4. Verify article bundle appears in content type facet with accurate counts
5. Spot-check container_home and stallion card/teaser display on `/search`
