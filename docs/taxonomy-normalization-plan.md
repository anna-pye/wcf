# Taxonomy Normalization Plan

**Date:** 2026-05-20  
**Branch:** `feature/editorial-governance-stabilization`  
**Scope:** Recommendations only — **no automatic legacy import beyond governed categories**

## D11 current state (evidence)

| Item | Value |
|------|-------|
| Vocabulary | `categories` (governed editorial classification) |
| Term seeding | `scripts/wcf-governed-categories.php` |
| Stallion `field_category` | Governed single-select; `auto_create: false` |
| Facet | `facets.facet.categories` on `/search` (hidden when empty) |
| Stallions View | SQL category filter on `/stallions` (`field_category_target_id`) |

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
| Foals / Broodmares / Stallions / show mares / ASB Stallions | **Optional** governed `categories` terms (already seeded) | Align with editorial handbook; do not bulk-import from D7 |
| For Sale | **Rejected** → use `field_status=active` | Redundant with status facet |
| Product `sold` flag | **Already mapped** → `field_status` | sold/active |
| Product `year` column | **Defer** | Use editorial title year or future structured field if required |
| D7 `field_tags` on nodes (rare) | **One-time** `scripts/wcf-migrate-tags-to-categories.php` then remove legacy tags config | Bridge only |

### Controlled mapping (if business mandates category labels later)

| Legacy label | Proposed D11 target | Condition |
|--------------|---------------------|-----------|
| Foals | `categories` term `Foals` | Already in governed seed list |
| Broodmares | `categories` term `Broodmares` | Same |
| For Sale | **Rejected** → use `field_status=active` | Redundant with status facet |
| Stallions / ASB Stallions | **Rejected** → bundle is already stallion | Redundant |

## Rejected mappings

- Importing all `wcf_category` rows into `categories` without editorial workshop (facet noise, inconsistent labels on 270+ stallions)
- Creating `wcf_category` vocabulary in D11 (duplicate of Views + status facet)
- Mapping categories to separate content types (architecture stable)
- Reintroducing `tags` vocabulary as current governance

## Deferred legacy structures

| Structure | Reason |
|-----------|--------|
| `wcf_category` table | Operational categories, not editorial classification |
| `wcf_year` / year column filters | Front-page SQL filters in D7; D11 uses Search/Views — needs product owner decision |
| D7 duplicate product aliases (18 slugs) | Path governance, not taxonomy |
| `field_asb_stallions` page references | Separate from product migration |

## Scalability concerns

| Risk | Mitigation |
|------|------------|
| Category explosion if legacy bulk-imported | Governed allow-list; cap at &lt;25 terms (`docs/taxonomy-scale-readiness.md`) |
| Dual filter UX (`/stallions` SQL vs `/search` facet) | Editorial onboarding doc |
| `show mares` capitalization | Normalized in governed seed (`Show Mares`) |

## Facet impact analysis

| Change | Impact on `/search` |
|--------|---------------------|
| Status quo (no bulk import) | `categories` facet hidden until indexed content has `field_category` |
| Assign governed terms to content | Facet shows ≤6 values; low cardinality |
| Import 200+ ungoverned labels | **High risk** — facet UI unusable |

**Recommendation:** Use `field_status` + fulltext search for discovery until editors assign governed categories. Run `wcf-governed-categories.php` before editorial onboarding.

## Editorial governance recommendations

1. **Category creation:** Restrict to `administer taxonomy`; maintain allowed list via `wcf-governed-categories.php`.
2. **No legacy category import** without workshop mapping Foals/Broodmares to business rules.
3. **Before bulk category assignment:** Run `ddev drush search-api:reset-tracker stallion_content` after saves.
4. **Review** `docs/taxonomy-scale-readiness.md` when term count exceeds 25.

## Validation commands

```bash
# D11
ddev drush sql:query "SELECT vid, COUNT(*) FROM taxonomy_term_field_data GROUP BY vid;"
ddev drush php:script scripts/wcf-governed-categories.php

# D7 legacy
cd ~/drupal7-legacy && ddev drush sql:query "SELECT id, category_title, alias FROM wcf_category;"
```
