# Taxonomy Scale Readiness

**Date:** 2026-05-19  
**Scope:** `tags` vocabulary, facet scalability, expansion risks  
**Approach:** Recommendations only — no architecture rebuild.

## Current state (evidence)

| Item | Value |
|------|-------|
| Vocabulary | `tags` (`taxonomy.vocabulary.tags.yml`) |
| Description | “Group articles on similar topics…” (legacy copy; also used on stallions/container homes) |
| Term count (DB) | 1 (`sf`) |
| Field | `field_tags` on `stallion`, `container_home`, `article` |
| Search index field | `tags` (integer, entity reference IDs) |
| Facet | `facets.facet.tags` — links widget, `query_operator: or`, `min_count: 1` |
| Stallions View filter | `taxonomy_index_tid` select, `limit: true` |

## Facet scalability assessment

| Factor | Current config | Scale risk |
|--------|----------------|------------|
| Widget | Links with counts | High if term count > ~30–50 (long sidebar) |
| `soft_limit` | 0 (unlimited) | No collapse for large vocabularies |
| `hard_limit` | null | No cap on facet terms returned |
| `min_count` | 1 | Hides unused terms (good) |
| Hierarchy | disabled | Flat list—OK for tags |

**Recommendation (future, not now):** When tag count exceeds ~25 published terms with search presence, set `soft_limit: 10` with “Show more” on `facets.facet.tags` or switch to checkbox widget with collapsed fieldset for mobile.

## Dual filter surfaces

Tags are filterable in two places:

1. `/stallions` — SQL exposed filter (select dropdown)
2. `/search` — Search API facet (link list)

**Risk:** Editors may confuse tag filter on stallions page vs. site search.  
**Recommendation:** Document in editorial onboarding: tags on `/search` apply to all indexed bundles; `/stallions` tags filter stallions only.

## Vocabulary structure

- Single shared `tags` vocabulary across bundles — appropriate for cross-content discovery
- No hierarchical categories — correct for faceted tag model
- **No new vocabularies recommended** without business requirement

## Migration / D7 ingestion risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Tag explosion from legacy | Slow facet render, noisy sidebar | Pre-migration tag cleanup in D7; merge synonyms before import |
| Machine names vs. labels | Facet shows raw keys | Ensure `translate_entity` / term names imported correctly |
| Orphan term references | Index gaps | Run migrate validation + `search-api:reset-tracker` after import |
| Unused legacy tags | Clutter | `min_count: 1` hides zero-result terms |

## Future taxonomy expansion (recommendations only)

If business requires breed, discipline, or region taxonomies:

1. **Do not** add to `tags` — use dedicated vocabularies + dedicated fields
2. Add fields to index only after editorial sign-off
3. Add facets only when a single filter surface is agreed (prefer `/search` for cross-bundle)
4. Re-evaluate `stallions` SQL exposed filters vs. facet duplication before adding parallel filters

## Index size interaction

From performance audit: facet aggregation acceptable at current volume. Re-evaluate when indexed nodes > ~10k or unique tag terms > ~100.

## Readiness verdict

| Criterion | Status |
|-----------|--------|
| Structure supports scaling | Yes (flat tags + min_count) |
| Widget ready for 100+ terms | No (plan soft_limit / widget change) |
| Editorial clarity | Needs onboarding doc |
| Blocking staging? | No |
