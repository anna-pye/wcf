# Taxonomy Scale Readiness

**Date:** 2026-05-20  
**Scope:** `categories` vocabulary, facet scalability, expansion risks  
**Approach:** Recommendations only — no architecture rebuild.

## Current state (evidence)

| Item | Value |
|------|-------|
| Vocabulary | `categories` (`taxonomy.vocabulary.categories.yml`) |
| Description | Governed editorial categories; terms managed centrally |
| Term seeding | `scripts/wcf-governed-categories.php` (Foals, Broodmares, Stallions, ASB Stallions, Show Mares) |
| Field | `field_category` on `stallion`, `container_home`, `article` |
| Search index field | `category` (integer, entity reference IDs) |
| Facet | `facets.facet.categories` — links widget, `query_operator: or`, `min_count: 1` |
| Stallions View filter | `taxonomy_index_tid` select, `limit: true`, `vid: categories` |

## Facet scalability assessment

| Factor | Current config | Scale risk |
|--------|----------------|------------|
| Widget | Links with counts | High if term count > ~30–50 (long sidebar) |
| `soft_limit` | 0 (unlimited) | No collapse for large vocabularies |
| `hard_limit` | null | No cap on facet terms returned |
| `min_count` | 1 | Hides unused terms (good) |
| Hierarchy | disabled | Flat list—OK for governed categories |

**Recommendation (future, not now):** When category count exceeds ~25 published terms with search presence, set `soft_limit: 10` with “Show more” on `facets.facet.categories` or switch to checkbox widget with collapsed fieldset for mobile.

## Dual filter surfaces

Categories are filterable in two places:

1. `/stallions` — SQL exposed filter (select dropdown)
2. `/search` — Search API facet (link list)

**Risk:** Editors may confuse category filter on stallions page vs. site search.  
**Recommendation:** Document in editorial onboarding: categories on `/search` apply to all indexed bundles; `/stallions` category filter applies to stallions only.

## Vocabulary structure

- Single shared `categories` vocabulary across bundles — appropriate for cross-content discovery
- No hierarchical categories — correct for governed flat classification
- **No new vocabularies recommended** without business requirement

## Migration / D7 ingestion risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Category explosion from legacy | Slow facet render, noisy sidebar | Use governed list only; one-time `wcf-migrate-tags-to-categories.php` for legacy `field_tags` |
| Machine names vs. labels | Facet shows raw keys | Ensure `translate_entity` / term names imported correctly |
| Orphan term references | Index gaps | Run migrate validation + `search-api:reset-tracker` after import |
| Unused legacy terms | Clutter | `min_count: 1` hides zero-result terms |

## Future taxonomy expansion (recommendations only)

If business requires breed, discipline, or region taxonomies:

1. **Do not** add to `categories` — use dedicated vocabularies + dedicated fields
2. Add fields to index only after editorial sign-off
3. Add facets only when a single filter surface is agreed (prefer `/search` for cross-bundle)
4. Re-evaluate `stallions` SQL exposed filters vs. facet duplication before adding parallel filters

## Index size interaction

From performance audit: facet aggregation acceptable at current volume. Re-evaluate when indexed nodes > ~10k or unique category terms > ~100.

## Readiness verdict

| Criterion | Status |
|-----------|--------|
| Structure supports scaling | Yes (flat categories + min_count) |
| Widget ready for 100+ terms | No (plan soft_limit / widget change) |
| Editorial clarity | Needs onboarding doc |
| Blocking staging? | No |
