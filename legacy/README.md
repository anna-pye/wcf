# Legacy Drupal 7 site files

Before running `wcf_d7_file` / `wcf_d7_media_image`, copy the Drupal 7 public files directory into this tree:

```
legacy/
  sites/
    default/
      files/
        (D7 public files — images, styles, etc.)
```

Example (adjust source path to your D7 codebase):

```bash
mkdir -p legacy/sites/default/files
rsync -a /path/to/drupal7/sites/default/files/ legacy/sites/default/files/
```

`$settings['migrate_file_public_path']` points at `legacy/` (the D7 site root), matching core `d7_file` expectations (`sites/default/files/...`).

## Product module files (stallion migration)

Legacy horse images and PDFs live outside `sites/default/files`:

```bash
rsync -a /path/to/drupal7/sites/all/modules/product/files/ \
  legacy/sites/all/modules/product/files/
```

Required for `wcf_d7_file_product` and downstream stallion media migrations.
