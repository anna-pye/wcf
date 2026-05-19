# Staging Rehearsal (Dry Run)

**Date:** 2026-05-20  
**Branch:** `feature/stallion-migration-version-control` (merge target: `main`)  
**Type:** Operational dry run — **do not execute on production without backup**

Migration YAML and plugins are version-controlled in `web/modules/custom/wcf_migrate/` — see `docs/stallion-migration-version-control-audit.md`.

## Preconditions

- [ ] Database snapshot
- [ ] Code deployed to branch with stallion migrations (`wcf_d7_*_product*`, `wcf_d7_node_stallion`)
- [ ] `wcf_migrate` enabled (`ddev drush pm:list --filter=wcf_migrate`)
- [ ] Legacy DB available in DDEV (`legacy`, `wcf_` prefix)
- [ ] Legacy product files available at `~/drupal7-legacy/sites/all/modules/product/files/`
- [ ] `ddev drush cst` clean (except known `gin.settings` env delta)

## Stallion migration sequence (exact order)

Run on a **fresh staging DB** or after rollback (see [Rollback (clean re-run only)](#rollback-clean-re-run-only)).

### 1. Rsync legacy product files

Product images/PDFs are **not in Git**. Copy from the D7 legacy tree:

```bash
rsync -a ~/drupal7-legacy/sites/all/modules/product/files/ \
  ~/drupal11-upgrade/legacy/sites/all/modules/product/files/
```

**Validate:** file count roughly matches migration audit (~1149 unique refs; not all exist on disk). See `legacy/README.md`.

### 2. Import product file migration

```bash
ddev drush cr
ddev drush mim wcf_d7_file_product -y
```

**Validate:** `ddev drush migrate:status wcf_d7_file_product` — expect many **unprocessed** rows when source files are missing (~796 typical); imported count should match readable files on disk.

### 3. Import product media migrations

```bash
ddev drush migrate:import wcf_d7_media_image_product --force -y
ddev drush migrate:import wcf_d7_media_document_product --force -y
ddev drush migrate:import wcf_d7_media_remote_video_product --force -y
```

**Validate:** image media ~276 imported; document ~77; remote video ~4 (see `docs/stallion-migration-validation.md`).

### 4. Import stallion node migration

```bash
ddev drush migrate:import wcf_d7_node_stallion --force -y
```

**Validate:**

```bash
ddev drush migrate:status wcf_d7_node_stallion
# Expect: 276 imported, 2 message failures (empty titles ids 284–285)
ddev drush migrate:messages wcf_d7_node_stallion
ddev drush sql:query "SELECT COUNT(*) FROM node_field_data WHERE type='stallion';"
# Expect: 276
```

### 5. Search integrity + reindex

Prefer the integrity script over raw tracker reset (handles orphaned tracker rows):

```bash
ddev drush php:script scripts/wcf-search-integrity.php
ddev drush php:script scripts/wcf-search-integrity.php -- --apply --reindex
ddev drush search-api:status
```

**Validate:**

- `stallion_content` 100% complete
- Public query count = published indexable nodes (expect ~221 published stallions + other indexed bundles)
- **Critical:** Confirm published stallions without images are either indexed or explicitly accepted as search-excluded (currently ~10 published imageless nids may fail indexing)

### 6. Sitemap regeneration

```bash
ddev drush simple-sitemap:generate
# or
ddev drush cron
```

**Validate:** `/sitemap.xml` lists published stallion canonical URLs.

### 7. Cache rebuild

```bash
ddev drush cr
cd web/themes/custom/wcf_theme && npm ci && npm run build
ddev drush cr
```

### 8. Canonical URL governance (post-migration)

Run after stallion import when legacy short aliases coexist with `/stallions/*` Pathauto aliases.

```bash
# Step A — ensure 301 redirects exist (dry-run, then apply)
ddev drush php:script scripts/canonical-redirect-remediation.php
ddev drush php:script scripts/canonical-redirect-remediation.php -- --apply

# Step B — remove conflicting legacy path_alias rows (dry-run, then apply)
ddev drush php:script scripts/canonical-alias-governance.php
ddev drush php:script scripts/canonical-alias-governance.php -- --apply

ddev drush cr
```

**Validate:**

| Check | Expected |
|-------|----------|
| `curl -sI https://wcf11.ddev.site/vegas` | HTTP 301 → `/stallions/clients-foal-tilly` |
| `curl -sI https://wcf11.ddev.site/stallions/clients-foal-tilly` | HTTP 200 |
| Re-run alias governance dry-run | `aliases_planned_for_removal`: 0 |
| Redirect admin | Legacy → canonical rows intact |

**Rollback:** recreate legacy `path_alias` rows for affected nids; do not delete canonical `/stallions/*` aliases. See `docs/canonical-url-governance.md`.

### 9. Validation checks

| Check | Command / URL |
|-------|----------------|
| Config sync | `ddev drush cst` |
| Migrations idle | `ddev drush migrate:status` |
| Routes | `/`, `/stallions`, `/search` → 200 |
| Facets | `/search` counts |
| Unpublished leak | anonymous `/search` has 0 draft titles |
| Missing images admin | `/admin/content/health/missing-images` |
| Editorial audit | `ddev drush php:script scripts/editorial-audit.php` |
| Canonical redirects | `scripts/canonical-redirect-remediation.php` (dry-run) |
| Legacy alias cleanup | `scripts/canonical-alias-governance.php` (dry-run) |

## Rollback (clean re-run only)

**Destructive — staging only.** Reverse dependency order — from `docs/stallion-migration-rollback.md`:

```bash
ddev drush mr wcf_d7_node_stallion -y
ddev drush mr wcf_d7_media_remote_video_product -y
ddev drush mr wcf_d7_media_document_product -y
ddev drush mr wcf_d7_media_image_product -y
ddev drush mr wcf_d7_file_product -y
```

**Validate:** `ddev drush sql:query "SELECT COUNT(*) FROM node_field_data WHERE type='stallion';"` → 0 (or expected sample-only count).

Then re-run [Stallion migration sequence](#stallion-migration-sequence-exact-order) from step 1.

## Rollback integrity

- Rollback removes nodes, media, files in reverse order — **no orphan stallion nodes** if order followed
- Does **not** remove non-product WCF migrations (`wcf_d7_node_page`, etc.)
- Re-import preserves legacy nids (`wcf_product.id`)

## Repeatability

| Risk | Mitigation |
|------|------------|
| Missing files on disk | Document skipped count; `--force` on media import |
| Nid collision with sample content | Delete sample stallions before import |
| Duplicate path aliases | Run canonical redirect + alias governance scripts — see `docs/canonical-url-governance.md` |
| Search tracker drift | Always reset-tracker after bulk import |

## Dependency failures observed (local)

- `wcf_d7_file_product`: 796 unprocessed file rows (missing source files) — expected
- 10 published stallions without main image remain absent from search after reindex — **requires images or Search API config fix**

## Estimated duration (staging)

| Step | Approx. |
|------|---------|
| Rsync files | 2–10 min |
| Migration import | 5–15 min |
| Search reindex | 1–3 min |
| Theme build + cache | 2 min |
| QA pass | 15–30 min |

## Related docs

- `docs/stallion-migration-version-control-audit.md`
- `docs/canonical-url-governance.md`
- `docs/stallion-migration-rollback.md`
- `docs/staging-deployment-checklist.md`
- `docs/stallion-migration-validation.md`
- `docs/stallion-migration-audit.md`
