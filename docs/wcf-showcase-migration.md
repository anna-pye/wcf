# WCF Showcase (D7 → D11)

**Date:** 2026-05-20  
**D7 source:** `sites/all/modules/showcase` + table `wcf_showcase`  
**D11 destination:** Content type `wcf_showcase_item` + View `wcf_showcase` at `/showcase`

## Legacy behaviour (D7)

| Item | Detail |
|------|--------|
| Storage | Custom table `wcf_showcase` (6 published rows, ids 6–11) |
| Public URL | `/showcase` (menu + footer) |
| Admin | `/admin/showcase` — title, rich text description, up to 8 images, active flag |
| Images | Module directory `files/showcase_images/` with derivatives `imgN_278_*`, `imgN_647_*` |
| Front | Single page listing all active rows; Fancybox lightbox per story |

Showcase is **not** a horse category and is unrelated to `wcf_product` / stallion taxonomy.

## D11 architecture

| Concern | Implementation |
|---------|----------------|
| Editorial | Node bundle `wcf_showcase_item`: `title`, `body`, `field_gallery` (Media image), `field_showcase_weight`, `field_showcase_legacy_id` |
| Public page | View `wcf_showcase` page display, path `/showcase`, intro header (D7 welcome copy) |
| Lightbox | Reuses `wcf_theme/gallery-lightbox` (GLightbox), per-story group `showcase-{nid}` |
| Menu | Theme front nav + footer link to `view.wcf_showcase.page_1` |

Homepage `feature_card` paragraphs remain separate (marketing strip), not a replacement for `/showcase`.

## Import

1. Copy D7 images (once):

   ```bash
   rsync -a ../drupal7-legacy/sites/all/modules/showcase/files/showcase_images/ tmp/showcase-images/
   ```

2. Export D7 rows (once):

   ```bash
   cd ../drupal7-legacy && ddev mysql -N -B -e "SELECT id, title, HEX(description), status, created_on, modified_on, product_img1, product_img2, product_img3, product_img4, product_img5, product_img6, product_img7, product_img8 FROM wcf_showcase WHERE status=1 ORDER BY id" > ../drupal11-upgrade/tmp/showcase-data.tsv
   ```

3. Import into D11:

   ```bash
   ddev drush cim -y
   ddev drush php:script scripts/wcf-import-showcase.php
   ddev drush cr
   ```

4. Remove legacy redirect if present (`showcase` → `/`):

   ```bash
   ddev drush sql:query "DELETE FROM redirect WHERE redirect_source__path='showcase' AND redirect_redirect__uri='internal:/'"
   ```

Re-run import is idempotent (matches `field_showcase_legacy_id`).

## Migrate API

`WcfD7Showcase` source plugin (`wcf_d7_showcase`) documents the legacy table for future Migrate runs; file copy is handled by the import script, not raw SQL porting.

## Validation

```bash
ddev drush ev "echo count(\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'wcf_showcase_item', 'status' => 1]));"
curl -sI https://wcf11.ddev.site/showcase | head -1
```

Expected: `6` nodes, `HTTP/2 200`.
