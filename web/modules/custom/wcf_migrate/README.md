# WCF Migrate

Controlled Drupal 7 → Drupal 11 migrations for the WCF rebuild. Does **not** recreate architecture, custom SQL tables, or `content_slider` nodes.

## Prerequisites

1. **Legacy database in D11 DDEV** — database `legacy` with D7 dump (table prefix `wcf_`):
   - `.ddev/config.yaml` → `additional_databases: [legacy]`
   - `web/sites/default/settings.migrate.php` → `$databases['migrate']` (included from `settings.php`)
   - Import: `./scripts/wcf-import-legacy-database.sh` (exports from `~/drupal7-legacy` DDEV by default)
2. `$settings['migrate_file_public_path']` → project `/legacy` directory (D7 public + product files).
3. Destination vocabularies, media types, and content types already exist in config sync.
4. `ddev drush en wcf_migrate -y` and `ddev drush cr`.

## Migration execution order

Run migrations in this order (dependencies are declared in YAML; this list is the safe manual sequence):

| Step | Migration ID | Purpose |
|------|----------------|---------|
| 1 | `wcf_d7_taxonomy_term_tags` | Tags vocabulary terms |
| 2 | `wcf_d7_file` | Public image files from D7 field references |
| 3 | `wcf_d7_media_image` | `media` image entities from migrated files |
| 4 | `wcf_d7_node_page` | Basic page nodes (20 expected) |
| 5 | `wcf_d7_node_container_home` | Container home nodes (3 expected) |
| 6 | `wcf_d7_file_product` | Product module image/PDF files |
| 7 | `wcf_d7_media_image_product` | Product images → media |
| 8 | `wcf_d7_media_document_product` | Product PDFs → document media |
| 9 | `wcf_d7_media_remote_video_product` | YouTube embeds → remote video media |
| 10 | `wcf_d7_node_stallion` | `wcf_product` → stallion nodes (278 expected) |

### Post-migrate: stallion category mapping

`category_id` is **not** set during `wcf_d7_node_stallion` import. After the stallion pipeline completes, map legacy categories with idempotent scripts (no re-import):

| Step | Script / action | Purpose |
|------|-----------------|---------|
| 1 | `scripts/wcf-governed-categories.php` | Seed governed `categories` terms (Foals, Broodmares, Stallions, ASB Stallions, Show Mares, For Sale) |
| 2 | Stallion migration validation | `docs/stallion-migration-validation.md` — counts, maps, media |
| 3 | `scripts/wcf-map-product-categories.php` | D7 `wcf_product.category_id` → `field_category` on stallion nodes |
| 4 | `scripts/wcf-map-product-display-names.php` | D7 `wcf_product.product_name` → `field_display_name` |
| 5 | `ddev drush cr` | Rebuild caches |
| 6 | Category filter QA | `/stallions` exposed filter — see `docs/audits/d7-custom-module-comparison/phase-2-category-mapping-validation.md` |

```bash
ddev drush php:script scripts/wcf-governed-categories.php
ddev drush php:script scripts/wcf-map-product-categories.php
ddev drush php:script scripts/wcf-map-product-display-names.php
ddev drush cr
```

Optional: `--force` on the mapping script overwrites existing `field_category` values (documented in script header).

## Commands

```bash
# Enable (once)
ddev drush en wcf_migrate -y
ddev drush cr

# Import in order
ddev drush mim wcf_d7_taxonomy_term_tags
ddev drush mim wcf_d7_file
ddev drush mim wcf_d7_media_image
ddev drush mim wcf_d7_node_page
ddev drush mim wcf_d7_node_container_home

# Stallion pipeline (after legacy product files rsync — see legacy/README.md)
ddev drush mim wcf_d7_file_product
ddev drush migrate:import wcf_d7_media_image_product --force
ddev drush migrate:import wcf_d7_media_document_product --force
ddev drush migrate:import wcf_d7_media_remote_video_product --force
ddev drush migrate:import wcf_d7_node_stallion --force

# Or all WCF migrations (respects dependencies)
ddev drush mim --tag=WCF

# Status
ddev drush ms --group=wcf_migrate

# Rollback (reverse order)
ddev drush mr wcf_d7_node_container_home
ddev drush mr wcf_d7_node_page
ddev drush mr wcf_d7_media_image
ddev drush mr wcf_d7_file
ddev drush mr wcf_d7_taxonomy_term_tags

# Stallion rollback (reverse order)
ddev drush mr wcf_d7_node_stallion
ddev drush mr wcf_d7_media_remote_video_product
ddev drush mr wcf_d7_media_document_product
ddev drush mr wcf_d7_media_image_product
ddev drush mr wcf_d7_file_product
```

## Field mapping notes (audited)

### Container homes (`compliant_container_homes` → `container_home`)

| D7 field | D11 field | Notes |
|----------|-----------|--------|
| `body` | `body` | Format mapped to D11 text formats |
| `field_containerimage` | `field_main_image` | Media reference via `wcf_d7_media_image` |
| `field_additional_images` | `field_gallery` | Media reference (multi) |
| `field_sold` | `field_sold` | **Not present on D7 bundle** — not migrated |
| `field_tags` | `field_tags` | **Not present on D7 bundle** — not migrated |

### Pages (`page` → `page`)

| D7 field | D11 field | Notes |
|----------|-----------|--------|
| `body` | `body` | |
| `path alias` | `path.alias` | From `url_alias` |
| `field_main_image` | — | D7 only; D11 `page` has no image field |
| `field_sold` | — | D7 only; D11 `page` has no boolean field |
| `field_asb_stallions` | — | Deferred / not in rebuild scope |
| `metatags_quick` | — | Ignored per plan |
| `snippets_code` | — | Deferred per plan |

## Deferred

- `content_slider` → `hero_slide` paragraphs: see `docs/HERO_SLIDER_MIGRATION_STRATEGY.md`
- Custom SQL tables (`wcf_product`, `wcf_showcase`, `wcf_banner`, `wcf_testimonial`, `wcf_nomination`, `wcf_news`) — replaced by modern content architecture (see below)
- Stallion migration: see `docs/stallion-migration-audit.md`, `docs/stallion-migration-validation.md`, `docs/stallion-migration-rollback.md`
- `snippets_code`, `metatags_quick`

## Modern business content (D11 foundation)

Established via `scripts/wcf-business-content-setup.php` (config in sync):

| Legacy system | D11 replacement |
|---------------|-----------------|
| `wcf_product` / horse content | `stallion` content type (media-first, revisionable) |
| `wcf_testimonial` | `testimonial_item` paragraph on `homepage` |
| `wcf_showcase` / `wcf_banner` | `feature_card` paragraph on `homepage` |
| `wcf_news` (newsletter PDFs) | `wcf_newsletter` nodes + document media; View `wcf_news` at `/news` |

Stallion node/media migration: `wcf_d7_node_stallion` and related migrations. Category assignment: post-migrate scripts above (Phase 2).
