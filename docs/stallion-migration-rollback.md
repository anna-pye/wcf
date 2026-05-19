# Stallion migration rollback

**Module:** `wcf_migrate`  
**Branch:** `feature/stallion-migration`

## Rollback order (required)

Rollback in **reverse dependency** order:

```bash
ddev drush mr wcf_d7_node_stallion
ddev drush mr wcf_d7_media_remote_video_product
ddev drush mr wcf_d7_media_document_product
ddev drush mr wcf_d7_media_image_product
ddev drush mr wcf_d7_file_product
```

## Per-step effects

| Migration | Removes |
|-----------|---------|
| `wcf_d7_node_stallion` | All migrated `stallion` nodes (preserved nids from `wcf_product.id`) |
| `wcf_d7_media_remote_video_product` | Remote video media from product embeds |
| `wcf_d7_media_document_product` | Document media from PDFs |
| `wcf_d7_media_image_product` | Image media from product files |
| `wcf_d7_file_product` | Files under `public://wcf_product/` |

**Does not remove:** `wcf_d7_file`, `wcf_d7_media_image`, `wcf_d7_node_page`, or other WCF migrations.

## Media rollback notes

- Media entities created by product migrations reference files in `public://wcf_product/`.
- Rolling back `wcf_d7_file_product` deletes file entities; ensure no manual media still references those files.
- If rollback fails on dependency, use `--force` on `migrate:rollback` (migrate_tools) after stopping imports.

## Re-run after rollback

```bash
# Ensure legacy product files present
rsync -a ~/drupal7-legacy/sites/all/modules/product/files/ legacy/sites/all/modules/product/files/

ddev drush cr
ddev drush mim wcf_d7_file_product -y
ddev drush migrate:import wcf_d7_media_image_product --force -y
ddev drush migrate:import wcf_d7_media_document_product --force -y
ddev drush migrate:import wcf_d7_media_remote_video_product --force -y
ddev drush migrate:import wcf_d7_node_stallion --force -y
```

Use `--force` on media/node imports if `wcf_d7_file_product` shows unprocessed rows (missing files skipped in source).

## Reindex workflow

After rollback or re-import:

```bash
ddev drush search-api:reset-tracker stallion_content
ddev drush search-api:index stallion_content
```

## Cache rebuild workflow

```bash
ddev drush cr
cd web/themes/custom/wcf_theme && npm run build
```

## Pre-migration sample content

If a sample `stallion` node existed before migration (non-legacy nid), remove it before re-import to avoid nid conflicts with preserved `wcf_product.id` values.
