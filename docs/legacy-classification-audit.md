# Legacy Classification Model Audit (D7 → D11)

**Date:** 2026-05-19  
**Branch:** `feature/legacy-classification-model`  
**Phase:** Audit only — no config entities, no vocabulary import, no Views changes  
**Evidence:** `~/drupal7-legacy` (DDEV DB + code), `~/drupal11-upgrade` (`config/sync`, `wcf_migrate`, live counts)

---

## Classification inventory

| Classification | Source | Count (published) | Current D11 mapping | Recommended model |
|----------------|--------|-------------------|---------------------|-------------------|
| **Stallions** | `wcf_category` alias `stallions` (`category_id=6`) | 5 products | `node:stallion` bundle; `/stallions` View (no category filter) | Same bundle; optional controlled tag `Stallions` if editorial approves |
| **ASB Stallions** | `wcf_category` alias `asb_stallions` (`id=7`); parallel D7 View `asb_stalions` on 2 page nodes | 7 products; 2 page nodes | Same `stallion` bundle; no ASB marker | Tag or future list field `ASB`; **not** separate bundle or View |
| **For Sale** | `wcf_category` alias `for-sale` (`id=2`) | 57 products (all `sold=1` — listing query anomaly) | `field_status` active/sold only; no category | `field_status=active` + editorial rules; **reject** category-as-tag |
| **Showcase** | `wcf_showcase` table; menu `/showcase` | 6 showcase rows | `field_featured` + homepage `feature_card` paragraphs | Marketing flag (`field_featured`); **not** taxonomy |
| **Foals** | `wcf_category` alias `foals` + `year` column | 128 products; years 2008–2025 | `stallion` bundle; `year` not migrated | Tag `Foals` (governed) + deferred `field_year` if year-nav required |
| **Agistment** | Menu → `agistment`; D7 `page` node (nid 6) | 1 page | `node:page` via `wcf_d7_node_page` | Separate `page` bundle — **not** horse classification |
| **Broodmares** | `wcf_category` alias `broodmares` (`id=4`) | 22 products | `stallion` bundle; no tag | Optional governed tag `Broodmares` |
| **Sold** | `wcf_product.sold=1`; virtual route `category/sold` | 93 sold (published); 73 on virtual sold page (non-foals) | `field_status=sold` (117 migrated) | **Lifecycle** via `field_status` — not taxonomy |
| **Sold Stallions** | View `sold_stalions` on page nodes; menu `sold-stallions` | 4 page nodes (Views layer) | Subset of `field_status=sold` on `stallion` | View filter: `field_status=sold` (+ optional ASB tag); **no** page-node duplicate |
| **Compliant Container Homes** | Menu + homepage static tiles; D7 path `compliant_container_homes` | Not in `wcf_product` | `node:container_home` bundle | **Separate business line** — keep `container_home` bundle |
| **show mares** | `wcf_category` alias `show-mares` (`id=5`) | 2 products | None | Optional tag if section retained; menu commented in D7 theme |

**D11 live counts (2026-05-19):** 221 published stallions; `field_status` active=147, sold=117; `field_tags` on stallions=0; `tags` vocabulary=1 term.

---

## 1. Legacy architecture findings

### 1.1 Primary horse storage: custom SQL, not nodes

Legacy horse inventory lives in **`wcf_product`** (278 rows total; 223 published), managed by the custom **Product** module. Drupal nodes are **not** the canonical store for foals, broodmares, for-sale horses, or most stallions.

| Table | Rows | Role |
|-------|------|------|
| `wcf_product` | 278 / 223 published | Single horse record per row |
| `wcf_category` | 6 flat buckets | Section FK (`category_id`) |
| `wcf_showcase` | 6 published | Separate homepage gallery entity |
| `wcf_featured_horse` | 1 | Legacy featured slot (theme commented out) |

**Schema:** each product has one `category_id`, optional `year`, `sold` (0/1), `status` (0/1), `alias`. No Drupal taxonomy on products.

### 1.2 Secondary stallion system: D7 page nodes + Views

| View | Path | Filters | Count |
|------|------|---------|------:|
| `stalions` | `unsold-stallions` | `page`, `field_asb_stallions=0`, `field_sold=0` | 1 |
| `sold_stalions` | `sold-stallions` | `page`, `field_sold=1` | 4 |
| `asb_stalions` | `asb-stallions` | `page`, `field_asb_stallions=1` | 2 |

Theme nav also links to `category/asb_stallions` and `category/stallions` ( **`wcf_product`** — different data). Six product aliases overlap page node titles.

### 1.3 Non-product classifications

| Section | Storage | Overlaps `wcf_product`? |
|---------|---------|-------------------------|
| **Showcase** | `wcf_showcase` | No FK to products |
| **Agistment** | `page` node nid 6 | No |
| **Compliant Container Homes** | Static homepage + path alias (no product module) | No — separate business line |
| **Sold Stallions** | View on page nodes | Title overlap only |

### 1.4 D7 fields audited (not Field API on products)

| D7 signal | Column/field | D11 equivalent |
|-----------|--------------|----------------|
| Published | `wcf_product.status` | `node.status` + moderation |
| Sold | `wcf_product.sold` | `field_status` (mapped in migrate) |
| Section | `category_id` → `wcf_category` | **Not migrated** |
| Foal year | `year` | **Deferred** |
| Featured (blocks) | `new_launch`, `best_seller` | Columns absent in current schema (dead code) |
| ASB (pages only) | `field_asb_stallions` | No D11 field |

**Not present on D7 products:** `field_status`, `field_featured`, Drupal taxonomy `wcf_category`.

---

## 2. `wcf_category` detail

| id | Title | alias | Published products |
|----|-------|-------|-------------------:|
| 1 | Foals | `foals` | 128 |
| 2 | For Sale | `for-sale` | 57 |
| 4 | Broodmares | `broodmares` | 22 |
| 5 | show mares | `show-mares` | 2 |
| 6 | Stallions | `stallions` | 5 |
| 7 | ASB Stallions | `asb_stallions` | 7 |

**Sold within category (published):**

| Category | active (`sold=0`) | sold (`sold=1`) |
|----------|------------------:|----------------:|
| foals | 108 | 20 |
| for-sale | 0 | 57 |
| broodmares | 13 | 9 |
| stallions | 3 | 2 |
| asb_stallions | 3 | 4 |
| show-mares | 1 | 1 |

**Default listing query:** `category_id` + `status=1` + **`sold=0`** (excludes sold from section lists).

**Virtual Sold page** (`category/sold`): all non-foal products with `sold=1` → 73 published.

---

## 3. Classification type analysis

| Type | Legacy examples | Model in D11 |
|------|-----------------|--------------|
| **Lifecycle state** | `sold`, virtual Sold page, D7 `field_sold` on pages | `field_status` (active / sold / retired) |
| **Business type / section** | Foals, Broodmares, ASB, Stallions categories | Deferred: governed tag or list field — **not** separate bundles |
| **Inventory anomaly** | For Sale category with all rows `sold=1` | Requires product-owner clarification before mapping |
| **Marketing / curation** | Showcase table, homepage ASB strip, `new_launch` blocks | `field_featured`, homepage paragraphs |
| **Discovery facet candidate** | Status, content type | Already faceted on `/search` |
| **Discovery facet — reject** | Category names as tags (6 values, 0 editorial discipline) | Would mislead; 0 stallions tagged today |
| **Homepage curation** | ASB SQL strip, container home tiles | Paragraphs + `field_featured`; static container_home bundle |
| **Non-horse content** | Agistment, Compliant Container Homes | `page` / `container_home` bundles |

---

## 4. D7 menu and URL structure

```
Home
Stallion ▼
  ├── category/asb_stallions
  └── category/stallions
category/for-sale
showcase
category/foals
agistment
category/broodmares
category/sold          (virtual)
sold-stallions         (Views on pages)
nomination
compliant_container_homes
```

| Pattern | Handler |
|---------|---------|
| `category/{alias}` | Product category listing |
| `category/foals/{year}` | Foals year filter |
| `product/view/{alias}` | Product detail |
| `showcase` | Showcase module |
| `sold-stallions`, `asb-stallions` | Views on page nodes |

**D11 canonical paths:** `/stallions/{alias}` (migration preserves legacy slugs); Pathauto pattern `/stallions/[title]` for new content.

---

## 5. D11 current mapping (evidence)

| Capability | Implementation |
|------------|----------------|
| Horse entity | `node:stallion` (single bundle) |
| Lifecycle | `field_status`: active, sold, retired |
| Highlight | `field_featured` (boolean) |
| Cross-cutting labels | `field_tags` → `tags` vocab (empty on migrated stallions) |
| Stallion listing | View `stallions` at `/stallions` |
| Site search | Search API `stallion_content` + facets at `/search` |
| Container homes | `node:container_home` |
| Migration | `sold` → `field_status`; **no** `category_id`, `year`, or tags |

---

## 6. What should NOT be modeled

| Legacy item | Reason to reject |
|-------------|------------------|
| 6 `wcf_category` rows → `tags` bulk import | Facet noise; duplicates bundle + status; 0 tags on 221 stallions |
| Separate node types per section | Breaks Search API index; duplicates fields |
| Recreate D7 page+Views stallion layer | Dual source of truth (7 pages vs 278 products) |
| `For Sale` as taxonomy | Conflicts with `field_status`; data anomaly (all sold=1) |
| Showcase as stallion category | Wrong entity; D11 uses `field_featured` + paragraphs |
| `/category/{alias}` URL structure | Canonical strategy is `/stallions/{alias}` |
| `new_launch` / `best_seller` columns | Not in current D7 schema |

---

## 7. Phase 3 implementation decision

**No implementation in this phase.**

| Proposed change | Verdict | Rationale |
|-----------------|---------|-----------|
| New `horse_section` vocabulary | **Rejected** | Duplicates deferred tag strategy; no editorial governance yet |
| Bulk category → tag migration | **Rejected** | `taxonomy-normalization-plan.md`; 0 tags today |
| `field_year` for foals | **Deferred** | Requires product-owner sign-off on year-nav UX |
| New Search API facet | **Rejected** | Status + type facets sufficient; tags facet hidden (empty) |
| ASB listing View | **Rejected** | Would duplicate `/stallions` + filters |
| Normalization script | **Rejected** | No mapping rules approved; for-sale anomaly unresolved |

---

## 8. Deferred / unknown items

| Item | Status |
|------|--------|
| For Sale: all published rows have `sold=1` vs listing query `sold=0` | Business clarification required |
| `year` for foals (128 products) | No D11 field |
| Retire D7 page+Views stallion duplicates | 7 nodes vs 278 products |
| `show mares` public section | 2 products; menu commented in D7 |
| `wcf_featured_horse` | 1 row; unused in theme |

---

## 9. Risks

| Risk | Impact |
|------|--------|
| Bulk category → tags without governance | Facet explosion; inconsistent discovery |
| Multiple horse bundles | Breaks index, doubles migration |
| Recreating `/category/*` URLs | Fights canonical `/stallions/*` strategy |
| Ignoring foal `year` | Loses year-tab UX unless replaced |
| Editors using `field_tags` free-form | Unbounded facet values (`auto_create: true`) |
| Treating Sold as category only | Loses cross-category sold archive behaviour |

---

## 10. Validation (Phase 5)

```bash
ddev drush cr          # success
ddev drush cim -y      # no changes to import
ddev drush cst         # no drift
ddev drush search-api:status  # stallion_content 100%, 278/278
npm run build --prefix web/themes/custom/wcf_theme  # success
```

| Route | HTTP |
|-------|------|
| `/` | 200 |
| `/stallions` | 200 |
| `/search` | 200 |

No config entities created. No Search API architecture changes.

---

## Related documentation

| Document | Purpose |
|----------|---------|
| `docs/classification-domain-model.md` | Target domain model (Phase 2) |
| `docs/discovery-governance.md` | Discovery rules (Phase 4) |
| `docs/taxonomy-normalization-plan.md` | Tag import deferral |
| `docs/discovery-architecture-audit.md` | Views vs Search API split |

## Appendix — Audit SQL

```bash
# D7 categories
cd ~/drupal7-legacy && ddev mysql -e "
SELECT c.id, c.alias, c.category_title, COUNT(p.id) cnt
FROM wcf_category c
LEFT JOIN wcf_product p ON p.category_id = c.id AND p.status = 1
GROUP BY c.id;"

# D11 status distribution
cd ~/drupal11-upgrade && ddev drush sql:query "
SELECT field_status_value, COUNT(*) FROM node__field_status GROUP BY field_status_value;"
```
