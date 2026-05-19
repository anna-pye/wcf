# WCF Migrate

Controlled Drupal 7 → Drupal 11 migrations for the WCF rebuild. Does **not** recreate architecture, custom SQL tables, or `content_slider` nodes.

## Prerequisites

1. Legacy database in DDEV (`legacy`, table prefix `wcf_`) — configured in `settings.ddev.php`.
2. `$settings['migrate_file_public_path']` points at the D7 site root containing `sites/default/files` (see `/legacy` in the project root).
3. Destination vocabularies, media types, and content types already exist in config sync.

## Migration execution order

Run migrations in this order (dependencies are declared in YAML; this list is the safe manual sequence):

| Step | Migration ID | Purpose |
|------|----------------|---------|
| 1 | `wcf_d7_taxonomy_term_tags` | Tags vocabulary terms |
| 2 | `wcf_d7_file` | Public image files from D7 field references |
| 3 | `wcf_d7_media_image` | `media` image entities from migrated files |
| 4 | `wcf_d7_node_page` | Basic page nodes (20 expected) |
| 5 | `wcf_d7_node_container_home` | Container home nodes (3 expected) |

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
- Legacy stallion/product node migration from D7 `wcf_product` — not yet implemented
- `snippets_code`, `metatags_quick`

## Modern business content (D11 foundation)

Established via `scripts/wcf-business-content-setup.php` (config in sync):

| Legacy system | D11 replacement |
|---------------|-----------------|
| `wcf_product` / horse content | `stallion` content type (media-first, revisionable) |
| `wcf_testimonial` | `testimonial_item` paragraph on `homepage` |
| `wcf_showcase` / `wcf_banner` | `feature_card` paragraph on `homepage` |

Stallion migration is **not** in scope until a dedicated migrate plugin maps D7 source data to `stallion` fields.
