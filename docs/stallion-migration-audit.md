# Stallion migration audit (D7 → D11)

**Date:** 2026-05-19  
**Branch:** `feature/stallion-migration`  
**Legacy DB:** DDEV `legacy` (`wcf_` table prefix)  
**D7 code reference:** `~/drupal7-legacy` (immutable)

## Executive summary

Legacy horse/stallion content lives in the **custom SQL table `wcf_product`**, not in Drupal 7 nodes. The D11 target is **`node:stallion`** with existing Media-first fields. Node-based `wcf_d7_image_file` / `wcf_d7_media_image` pipelines do **not** cover product images (module-stored files, not `file_managed`).

## Source tables

| Table | Rows | Role |
|-------|------|------|
| `wcf_product` | 278 | Primary horse/stallion business records |
| `wcf_category` | 6 | Legacy listing categories (Foals, Stallions, etc.) |
| `wcf_showcase` | 6 | Homepage showcase slots — **deferred** (replaced by D11 `feature_card` paragraphs) |
| `wcf_node` | 26 | Only `page`, `content_slider`, `compliant_container_homes` — **no stallion nodes** |
| `wcf_file_managed` | 147 | D7 field-based files — **not used by products** |

### `wcf_product` schema (audited columns)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint PK | Preserved as D11 `nid` |
| `product_name` | varchar | Display name |
| `title_with_year` | varchar | Preferred node title (2 rows empty) |
| `alias` | varchar | URL slug (18 duplicate slugs) |
| `category_id` | bigint | FK to `wcf_category` |
| `year` | varchar | Foal year filter — **deferred** |
| `product_img` | varchar | Main image filename |
| `product_img1`–`product_img4` | varchar | Gallery filenames |
| `description` | longtext | Body HTML/text |
| `youtube_iframe`, `youtube_iframe_2` | text | Raw iframe markup (5 rows) |
| `pdf_file` | varchar | Pedigree PDF filename |
| `sold` | int | 0=active, 1=sold |
| `status` | int | 0=unpublished, 1=published |
| `created_on`, `modified_on` | int | Unix timestamps |

### Legacy category distribution

| Category | Count |
|----------|------:|
| Foals | 135 |
| For Sale | 70 |
| Broodmares | 54 |
| ASB Stallions | 7 |
| Stallions | 6 |
| show mares | 4 |

**Note:** D11 uses a single `stallion` bundle for all legacy product types (per `wcf-business-content-setup.php`).

## Source bundles

| D7 “bundle” | Storage | D11 target |
|-------------|---------|------------|
| Product module horses | `wcf_product` | `node:stallion` |
| `wcf_showcase` | Custom table | `feature_card` paragraph (already on D11) — **not migrated here** |
| `field_asb_stallions` | D7 node field on `page` | **deferred** (editorial reference, not product rows) |

## Field mappings

| D7 source | D11 field | Status |
|-----------|-----------|--------|
| `title_with_year` → fallback `product_name` | `title` | Mapped |
| `description` | `body.value` | Mapped (`basic_html`) |
| — | `body.summary` | Empty (no D7 summary) |
| `product_img` (+ thumb fallback) | `field_main_image` → `media:image` | Mapped |
| `product_img1`–`product_img4` | `field_gallery` → `media:image` | Mapped |
| `sold` | `field_status` (`active` / `sold`) | Mapped |
| `status` | `status` + `moderation_state` | Mapped |
| `created_on` / `modified_on` | `created` / `changed` | Mapped |
| `alias` | `path/alias` → `/stallions/{slug}` | Mapped (duplicates suffixed `-{id}`) |
| `youtube_iframe` / `_2` | `field_video` → `media:remote_video` | Mapped (4 of 5) |
| `pdf_file` | `field_documents` → `media:document` | Mapped |
| `category_id` / `year` | — | **Deferred** (no D11 equivalent without new architecture) |
| — | `field_tags` | **Deferred** (no tags on `wcf_product`) |
| — | `field_cta_phone` | **Deferred** (not in `wcf_product`) |
| — | `field_featured` | Default `0` (no legacy flag) |

## Image / file mappings

| Legacy location | D11 |
|-----------------|-----|
| `sites/all/modules/product/files/product_images/{file}` | `public://wcf_product/{file}` → `media:image` |
| Same path, `thumb_350_{file}` etc. | Used when full-size file missing |
| `sites/all/modules/product/files/pdf_files/{file}` | `public://wcf_product/{file}` → `media:document` |

**Prerequisite:** Copy or rsync D7 product files into `legacy/sites/all/modules/product/files/` (see `legacy/README.md`).

**Not reused:** `wcf_d7_file` / `wcf_d7_media_image` (D7 `field_*` FIDs only).

## Taxonomy mappings

| D7 | D11 | Status |
|----|-----|--------|
| `wcf_category` | — | **Deferred** (listing categories, not free tags) |
| Product tags | `field_tags` | **Deferred** |

## Unresolved legacy issues

1. **2 product rows** (`id` 284, 285) have empty `product_name` and `title_with_year` — excluded from migration.
2. **~873 file references** have no readable source file (missing originals; only thumbs may exist).
3. **18 duplicate `alias` values** — migrated with `-{id}` suffix on path.
4. **Second YouTube embed** (`youtube_iframe_2`) — only primary/first non-empty embed migrated.
5. **Legacy URL pattern** `product/view/{alias}` → D11 `/stallions/{alias}` (pathauto pattern uses title for new edits).

## Deferred items

- `wcf_showcase` → homepage `feature_card` (separate content strategy)
- `wcf_category` / `year` → taxonomy or facets (needs editorial decision)
- `field_tags`, `field_cta_phone`, `field_featured` population
- `field_asb_stallions` on D7 pages
- `metatags_quick`, `snippets_code`

## Migration risks

| Risk | Mitigation |
|------|------------|
| Missing image files | Thumb fallback; nodes without `field_main_image` remain valid |
| Large file copy | rsync once into `legacy/`; migrate map per filename |
| NID collision with sample content | Preserved D7 `id`; remove sample stallion before re-run |
| Duplicate aliases | `wcf_d7_product_path_alias` suffix |
| Unpublished legacy rows | `status=0` → unpublished + `moderation_state=draft` |
| Search leakage | `entity_status` + `content_access` on `stallion_content` index |

## Rollback strategy

See `docs/stallion-migration-rollback.md`.

Order: `wcf_d7_node_stallion` → media migrations → `wcf_d7_file_product` (reverse of import).

## Validation checklist

- [x] Legacy DB audited (`wcf_product`, 278 rows)
- [x] D11 `stallion` fields confirmed in `config/sync`
- [x] Product files rsync’d into `legacy/`
- [x] Migrations implemented in `wcf_migrate`
- [x] 276/278 stallions imported
- [x] Search API reindexed (267 items across index bundles)
- [x] Config sync clean (`drush cst`)
- [ ] Manual browser check `/stallions`, `/search`, sample stallion paths
- [ ] Editorial review of 2 failed rows and unmigrated files
