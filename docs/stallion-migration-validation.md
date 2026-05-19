# Stallion migration validation

**Date:** 2026-05-19  
**Branch:** `feature/stallion-migration`  
**Environment:** DDEV `wcf11.ddev.site`

## Migrated counts

| Entity | Expected (legacy) | Imported | Notes |
|--------|------------------:|---------:|-------|
| `wcf_product` rows | 278 | 276 | 2 skipped (empty title, ids 284–285) |
| Published stallions | 223 | 221 | Matches legacy `status=1` minus failures |
| Unpublished | 55 | 55 | `status=0` |
| `wcf_d7_file_product` | 1149 unique refs | 276 | Readable files (+ thumb fallback) |
| `wcf_d7_media_image_product` | — | 276 | |
| `wcf_d7_media_document_product` | 78 | 77 | 1 PDF missing on disk |
| `wcf_d7_media_remote_video_product` | 5 | 4 | 1 embed URL not parseable |
| Search API `stallion_content` | — | 267 indexed | Includes stallion, article, container_home |

## Post-migration Drupal counts

```
stallion nodes:        276
published stallions:   221
field_status sold:     117
field_status active:   147
with field_main_image: 265
total nodes (all types): 288
```

## Missing records

| Source ID | Reason |
|-----------|--------|
| 284, 285 | Empty `product_name` and `title_with_year` |

## Image failures

- **~873** unique legacy filenames not imported (source file absent; many originals removed, thumbs only).
- **11** stallions without `field_main_image` after migration.
- Thumb fallback used when `thumb_350_*`, `thumb_405_*`, or `thumb_100_*` exists.

## Taxonomy validation

- `field_tags`: not populated (no D7 source on `wcf_product`).
- Legacy `wcf_category` not migrated.

## Search validation

```bash
ddev drush search-api:status
# stallion_content: 100% complete, 267 indexed

ddev drush search-api:reset-tracker stallion_content
ddev drush search-api:index stallion_content
```

- Index includes `stallion`, `article`, `container_home` bundles.
- Processors: `entity_status`, `content_access` (from existing config).

## Moderation validation

- Published legacy rows: `moderation_state=published`, `status=1`.
- Unpublished legacy rows: `moderation_state=draft`, `status=0`.
- Workflow: `editorial` on `stallion` bundle (existing config).

## Config / build validation

| Command | Result |
|---------|--------|
| `ddev drush cr` | OK |
| `ddev drush cim -y` | No changes |
| `ddev drush cst` | No differences |
| `npm run build` (wcf_theme) | OK |

## Route checks

Run from project root:

```bash
ddev exec curl -s -o /dev/null -w "%{http_code} /\\n" https://wcf11.ddev.site/
ddev exec curl -s -o /dev/null -w "%{http_code} /stallions\\n" https://wcf11.ddev.site/stallions
ddev exec curl -s -o /dev/null -w "%{http_code} /search\\n" https://wcf11.ddev.site/search
ddev exec curl -s -o /dev/null -w "%{http_code} sitemap\\n" https://wcf11.ddev.site/sitemap.xml
```

## Unresolved legacy issues

1. Empty-title products (2).
2. Bulk missing original image files on disk.
3. Category/year not represented on D11 nodes.
4. One YouTube embed not converted to oEmbed URL.

## Recommended next phase

1. Editorial pass: tag assignment, featured stallions, phone numbers where known.
2. Manual fix or archive legacy rows 284–285.
3. Optional: map `wcf_category` labels to `field_tags` terms (controlled vocabulary).
4. RUM/spot-check card/teaser rendering on `/stallions` and `/search`.
5. Staging import runbook with full `legacy/` rsync before production cutover.
