# SEO + Metatag Validation

**Date:** 2026-05-19  
**Branch:** `feature/editorial-normalization`  
**Architecture:** Metatag + Pathauto + Simple XML Sitemap (unchanged)

## Metatag configuration (stallion)

`metatag.metatag_defaults.node__stallion.yml`:

- `canonical_url`: `[node:url]`
- `description` / `og_description` / `twitter_cards_description`: `[node:summary]`
- `og_image` / `twitter_cards_image`: main image media URL
- `title`: `[node:title] | [site:name]`

## Summary / description quality

| Check | Result |
|-------|--------|
| Published stallions with body summary | 0 / 221 |
| Effective meta description at render | Empty for all published (inherits empty summary) |
| Per-node metatag overrides | None detected on sample nids |

**Severity:** High for organic/social sharing — **manual editorial** required.

## Canonical URLs

| Page type | Behavior | Status |
|-----------|----------|--------|
| Stallion (Pathauto) | `/stallions/{slug}` | PASS for primary alias |
| Legacy short alias | e.g. `/hagia-sophia` (nid 17) | Resolves 200 — **duplicate URL risk** |
| Homepage | `/` | PASS |
| Container home | Pathauto pattern | Spot-check pending if published |

### Duplicate alias inventory (stallions)

8 nodes have **legacy short alias** plus `/stallions/…` alias:

| nid | Legacy alias | Pathauto alias (example) |
|-----|--------------|---------------------------|
| 14 | `/vegas` | `/stallions/clients-foal-tilly` |
| 16 | `/moonlark` | `/stallions/gold-gisele` |
| 17 | `/hagia-sophia` | `/stallions/maybe-mabelline` |
| 18 | `/greasy-heel-2` | `/stallions/laughyoumay-18` |
| 25 | `/profile-in-style` | `/stallions/clients-foal-lola` |
| 28 | `/thomas_the_tank` | `/stallions/clients-foal-tba` |
| 31 | `/compliant_container_homes` | `/stallions/lilac-in-style-aka-kangarooby-winning-colours` |
| 37 | `/mega_charge` | *(Pathauto)* |

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Choose canonical `/stallions/…`; 301 legacy aliases in redirect module or remove duplicate aliases |
| **Safe automated** | None without redirect strategy approval |

## Open Graph / Twitter cards

Tags configured — **will render empty description** until summaries exist. Images present where `field_main_image` exists (265 nodes).

## Alias integrity

- 0 stallions without any alias
- Pathauto pattern `node_stallion` active
- **10 published stallions** missing from search index (SEO discovery gap on `/search`) — fix via Search API reindex

## Sitemap inclusion

Module: Simple XML Sitemap

```bash
# Regenerate
ddev drush simple-sitemap:generate
```

Manual validation:

- [ ] `https://wcf11.ddev.site/sitemap.xml` returns 200
- [ ] Published stallion URLs use preferred canonical path
- [ ] Unpublished (55) omitted

## Page-type checklist

| Page | Canonical | Meta description | OG image |
|------|-----------|------------------|----------|
| Homepage | PASS | Uses site defaults | Hero-dependent |
| Stallion (with image) | PASS* | FAIL (empty) | PASS |
| Stallion (no image) | PASS* | FAIL | FAIL |
| Container home | Verify when published | Verify | Verify |
| Article | No fixtures at audit | N/A | N/A |

\*Canonical PASS only after legacy alias consolidation.

## DO NOT

- Redesign SEO architecture
- Auto-generate descriptions without editorial approval

## Validation commands

```bash
ddev drush sql:query "SELECT n.nid, pa.alias FROM node_field_data n JOIN path_alias pa ON pa.path=CONCAT('/node/',n.nid) WHERE n.type='stallion' AND pa.alias NOT LIKE '/stallions/%';"
ddev drush simple-sitemap:generate
```
