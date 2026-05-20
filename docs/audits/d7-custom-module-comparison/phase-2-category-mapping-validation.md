# Phase 2 — Category Mapping Validation

**Date:** 2026-05-20  
**Scope:** Post-migrate `wcf_product.category_id` → `stallion.field_category` (governed `categories` vocabulary)  
**Approach:** Idempotent Drush PHP scripts — no stallion re-import, no migration rollback

---

## Decision

D7 **For Sale** (`category_id = 2`, 70 rows) maps to governed D11 term **For Sale** in vocabulary `categories`.

D7 `sub_category_id` is ignored (0 rows with `sub_category_id > 0` in production data).

---

## D7 → D11 mapping

| D7 `category_id` | D7 title | D11 term (`categories`) |
|----------------:|----------|---------------------------|
| 1 | Foals | Foals |
| 2 | For Sale | For Sale |
| 4 | Broodmares | Broodmares |
| 5 | show mares | Show Mares |
| 6 | Stallions | Stallions |
| 7 | ASB Stallions | ASB Stallions |

Stallion nodes use **preserved nids** (`nid: id` in `wcf_d7_node_stallion.yml`), so D7 `wcf_product.id` equals D11 `node.nid`.

---

## Script order

Run from project root (`~/drupal11-upgrade`):

| Step | Command | Purpose |
|------|---------|---------|
| 1 | `ddev drush php:script scripts/wcf-governed-categories.php` | Ensure governed terms exist (including **For Sale**) |
| 2 | Stallion migration validation | `docs/stallion-migration-validation.md` — counts, maps, media (read-only) |
| 3 | `ddev drush php:script scripts/wcf-map-product-categories.php` | Map `category_id` → `field_category` |
| 4 | `ddev drush cr` | Rebuild caches |
| 5 | Category filter QA | `/stallions` exposed filter + Search API (below) |

Optional overwrite (not default):

```bash
ddev drush php:script scripts/wcf-map-product-categories.php -- --force
```

`--force` replaces existing `field_category` values. Use only after stakeholder approval.

---

## Commands

### Syntax check

```bash
php -l scripts/wcf-governed-categories.php
php -l scripts/wcf-map-product-categories.php
```

### Before mapping (baseline)

```bash
# D7 product rows and category distribution
ddev drush sql:query --database=migrate \
  "SELECT COUNT(*) AS d7_products FROM wcf_product;"

ddev drush sql:query --database=migrate \
  "SELECT p.category_id, c.category_title, COUNT(*) AS cnt
   FROM wcf_product p
   LEFT JOIN wcf_category c ON c.id = p.category_id
   GROUP BY p.category_id, c.category_title
   ORDER BY p.category_id;"

# D11 stallions with field_category (expect 0 before Phase 2)
ddev drush sql:query \
  "SELECT COUNT(DISTINCT nfc.entity_id) AS stallions_with_category
   FROM node__field_category nfc
   JOIN node_field_data nfd ON nfc.entity_id = nfd.nid
   WHERE nfd.type = 'stallion'
     AND nfc.field_category_target_id IS NOT NULL
     AND nfc.field_category_target_id != 0;"

# Governed terms
ddev drush sql:query \
  "SELECT tid, name FROM taxonomy_term_field_data WHERE vid='categories' ORDER BY name;"
```

### Run mapping

```bash
ddev drush php:script scripts/wcf-governed-categories.php
ddev drush php:script scripts/wcf-map-product-categories.php
ddev drush cr
```

### After mapping

```bash
ddev drush sql:query \
  "SELECT COUNT(DISTINCT nfc.entity_id) AS stallions_with_category
   FROM node__field_category nfc
   JOIN node_field_data nfd ON nfc.entity_id = nfd.nid
   WHERE nfd.type = 'stallion'
     AND nfc.field_category_target_id IS NOT NULL
     AND nfc.field_category_target_id != 0;"

ddev drush sql:query \
  "SELECT t.name, COUNT(*) FROM node__field_category fc
   JOIN taxonomy_term_field_data t ON t.tid = fc.field_category_target_id
   JOIN node_field_data n ON n.nid = fc.entity_id
   WHERE n.type='stallion'
   GROUP BY t.name ORDER BY t.name;"
```

### JSON script output

The mapping script prints a JSON summary with:

- `legacy_rows_checked` — D7 `wcf_product` rows read (expect **278**)
- `nodes_found` — matching `stallion` nodes (expect **276**; ids **284**, **285** skipped at migrate)
- `categories_set` — nodes updated this run
- `already_populated` — skipped because `field_category` already set (unless `--force`)
- `missing_nodes` — D7 id with no `stallion` node
- `missing_terms` — governed terms absent (script exits **1**)
- `skipped_no_category` — D7 rows with `category_id = 0` (expect **2**: ids **284**, **285**, not imported)
- `unmapped_category_ids` — rows with unknown non-zero `category_id`

---

## Expected results

| Check | Expected |
|-------|----------|
| D7 `wcf_product` rows | **278** |
| D11 `stallion` nodes | **276** |
| Stallions with `field_category` after first run | **276** (all migrated stallions with a mapped `category_id`) |
| Unmapped D7 rows | **0** (all 278 rows use ids 1, 2, 4, 5, 6, 7) |
| Missing nodes | **2** (product ids **284**, **285** — empty title, not imported) |

### Category distribution (D11, after mapping)

Should mirror D7 counts on imported nodes:

| Term | Expected count |
|------|----------------:|
| Foals | 135 |
| For Sale | 70 |
| Broodmares | 54 |
| Show Mares | 4 |
| Stallions | 6 |
| ASB Stallions | 7 |

---

## `/stallions` exposed filter QA

View: `stallions` — exposed filter `stallion_category` on `field_category_target_id`.

- [ ] `/stallions` loads without errors
- [ ] Category filter shows all six governed terms
- [ ] Filter by **Foals** returns ~135 results
- [ ] Filter by **For Sale** returns ~70 results
- [ ] Filter by **Broodmares** returns ~54 results
- [ ] Combined filter + sort behaves as expected
- [ ] Stallion cards/teasers show correct category label where designed
- [ ] After `drush cr`, Search API index `stallion_content` includes `field_category` for new assignments (reindex if tracker stale: `scripts/wcf-search-integrity.php`)

Sample URLs:

```bash
# List all stallions
curl -sI "https://wcf11.ddev.site/stallions" | head -1

# Spot-check a Foal and For Sale node (adjust aliases from DB)
ddev drush sql:query \
  "SELECT n.nid, n.title, t.name FROM node_field_data n
   JOIN node__field_category fc ON fc.entity_id = n.nid
   JOIN taxonomy_term_field_data t ON t.tid = fc.field_category_target_id
   WHERE n.type='stallion' AND t.name IN ('Foals','For Sale') LIMIT 5;"
```

---

## Rollback notes

**Do not** roll back `wcf_d7_node_stallion` for category fixes — that risks media map breakage.

To clear only category assignments:

```bash
# Preview count
ddev drush sql:query \
  "SELECT COUNT(*) FROM node__field_category nfc
   JOIN node_field_data nfd ON nfc.entity_id = nfd.nid
   WHERE nfd.type = 'stallion';"

# Clear field_category on all stallions (destructive — staging only)
ddev drush php:eval "
\$nids = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'stallion')->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple(\$nids) as \$node) {
  if (!\$node->get('field_category')->isEmpty()) {
    \$node->set('field_category', []);
    \$node->setNewRevision(FALSE);
    \$node->save();
  }
}
print count(\$nids) . \" stallions processed.\n\";
"

ddev drush cr
```

Re-run governed categories + mapping scripts to restore.

Full stallion migration rollback remains documented in `docs/stallion-migration-rollback.md`.

---

## Related documents

| Document | Path |
|----------|------|
| Phase 1 plan | `phase-1-stallion-migration-action-plan.md` |
| Migrate README | `../../../web/modules/custom/wcf_migrate/README.md` |
| Stallion validation | `../../stallion-migration-validation.md` |
| Stallion rollback | `../../stallion-migration-rollback.md` |
