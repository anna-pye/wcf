# Taxonomy Normalization Plan

**Date:** 2026-05-19  
**Branch:** `feature/editorial-normalization`  
**Scope:** Recommendations only — **no vocabulary creation, no automatic legacy import**

## D11 current state (evidence)

| Item | Value |
|------|-------|
| Vocabulary | `tags` only (for cross-bundle faceting) |
| Term count | 1 (`sf`, tid 1) |
| Stallion `field_tags` | 0 populated on migrated products |
| Facet | `facets.facet.tags` on `/search` (hidden when empty) |
| Stallions View | SQL tag filter on `/stallions` |

## D7 legacy `wcf_category` (evidence)

**Table:** `wcf_category` (6 rows, all `status=1`)

| id | category_title | alias | Products |
|----|----------------|-------|----------:|
| 1 | Foals | foals | 135 |
| 2 | For Sale | for-sale | 70 |
| 4 | Broodmares | broodmares | 54 |
| 5 | show mares | show-mares | 4 |
| 6 | Stallions | stallions | 6 |
| 7 | ASB Stallions | asb_stallions | 7 |

- **Duplicate labels (case-insensitive):** none
- **Inconsistent capitalization:** `show mares` (lowercase “show”)
- **Products without category:** 2 (`category_id` NULL/0)
- **Hierarchy:** flat (`parent_id=0` for all)

Legacy categories are **listing buckets**, not hierarchical taxonomy. D11 uses bundle `stallion` + `field_status` (active/sold) + Views filters instead.

## Proposed mappings

| Legacy `wcf_category` | D11 approach | Rationale |
|----------------------|--------------|-----------|
| Foals / For Sale / Broodmares / Stallions / show mares / ASB Stallions | **Defer** — do not map 1:1 to `tags` | Would explode tag facet; duplicates business meaning already in titles/years |
| Product `sold` flag | **Already mapped** → `field_status` | sold/active |
| Product `year` column | **Defer** | Use editorial title year or future structured field if required |
| D7 `field_tags` on nodes (rare) | **Manual** import to `field_tags` only after synonym cleanup | 1 D11 term today |

### Controlled mapping (if business mandates category labels later)

| Legacy label | Proposed D11 target | Condition |
|--------------|---------------------|-----------|
| Foals | Optional tag `Foals` | Only if editorial approves tag governance rules |
| Broodmares | Optional tag `Broodmares` | Same |
| For Sale | **Rejected** → use `field_status=active` | Redundant with status facet |
| Stallions / ASB Stallions | **Rejected** → bundle is already stallion | Redundant |

## Rejected mappings

- Importing all `wcf_category` rows into `tags` without cleanup (facet noise, 270+ untagged stallions would gain inconsistent labels)
- Creating `wcf_category` vocabulary in D11 (duplicate of Views + status facet)
- Mapping categories to separate content types (architecture stable)

## Deferred legacy structures

| Structure | Reason |
|-----------|--------|
| `wcf_category` table | Operational categories, not editorial tags |
| `wcf_year` / year column filters | Front-page SQL filters in D7; D11 uses Search/Views — needs product owner decision |
| D7 duplicate product aliases (18 slugs) | Path governance, not taxonomy |
| `field_asb_stallions` page references | Separate from product migration |

## Scalability concerns

| Risk | Mitigation |
|------|------------|
| Tag explosion if legacy tags bulk-imported | Pre-merge synonyms; cap at &lt;25 terms before enabling visible facet (`docs/taxonomy-scale-readiness.md`) |
| Dual filter UX (`/stallions` SQL vs `/search` facet) | Editorial onboarding doc |
| `show mares` capitalization | Normalize label if term ever created |

## Facet impact analysis

| Change | Impact on `/search` |
|--------|---------------------|
| Status quo (no import) | `tags` facet remains hidden; `stallion_status` facet shows active/sold counts — **no change** |
| Import 6 category names as tags | +6 facet values; low cardinality but misleading (products lack historical tag discipline) |
| Import 200+ auto-tags from titles | **High risk** — facet UI unusable |

**Recommendation:** Keep facet architecture unchanged. Use `field_status` + fulltext search for discovery until editorial defines a controlled tag vocabulary (&lt;20 terms).

## Editorial governance recommendations

1. **Tag creation:** Restrict to `administer taxonomy` role; maintain allowed-tag list in editorial handbook.
2. **No legacy category import** without workshop mapping Foals/Broodmares to business rules.
3. **Before bulk tag assignment:** Run `ddev drush search-api:reset-tracker stallion_content` after tag saves.
4. **Review** `docs/taxonomy-scale-readiness.md` when term count exceeds 25.

## Validation commands

```bash
# D11
ddev drush sql:query "SELECT vid, COUNT(*) FROM taxonomy_term_field_data GROUP BY vid;"

# D7 legacy
cd ~/drupal7-legacy && ddev drush sql:query "SELECT id, category_title, alias FROM wcf_category;"
```
