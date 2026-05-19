# Staging Rehearsal (Dry Run)

**Date:** 2026-05-19  
**Branch:** `feature/editorial-normalization`  
**Type:** Operational dry run — **do not execute on production without backup**

## Preconditions

- [ ] Database snapshot
- [ ] Code deployed to staging branch
- [ ] Legacy product files available at `~/drupal7-legacy/sites/all/modules/product/files/`
- [ ] `ddev drush cst` clean (except known `gin.settings` env delta)

## Sequence (exact order)

### 1. Rsync legacy product files

```bash
rsync -a ~/drupal7-legacy/sites/all/modules/product/files/ \
  ~/drupal11-upgrade/legacy/sites/all/modules/product/files/
```

**Validate:** file count roughly matches migration audit (~1149 unique refs; not all exist on disk).

### 2. Rollback order (if re-run from clean migration state)

Reverse dependency order — from `docs/stallion-migration-rollback.md`:

```bash
ddev drush mr wcf_d7_node_stallion -y
ddev drush mr wcf_d7_media_remote_video_product -y
ddev drush mr wcf_d7_media_document_product -y
ddev drush mr wcf_d7_media_image_product -y
ddev drush mr wcf_d7_file_product -y
```

**Validate:** `ddev drush sql:query "SELECT COUNT(*) FROM node_field_data WHERE type='stallion';"` → 0 (or expected sample-only count).

### 3. Migration order

```bash
ddev drush cr
ddev drush mim wcf_d7_file_product -y
ddev drush migrate:import wcf_d7_media_image_product --force -y
ddev drush migrate:import wcf_d7_media_document_product --force -y
ddev drush migrate:import wcf_d7_media_remote_video_product --force -y
ddev drush migrate:import wcf_d7_node_stallion --force -y
```

**Validate:**

```bash
ddev drush migrate:status | grep wcf_d7_node_stallion
# Expect: 276 imported, 2 unprocessed (empty titles ids 284–285)
ddev drush sql:query "SELECT COUNT(*) FROM node_field_data WHERE type='stallion';"
# Expect: 276
```

### 4. Search API reindex

```bash
ddev drush search-api:reset-tracker stallion_content
ddev drush search-api:index stallion_content
ddev drush search-api:status
```

**Validate:**

- 100% complete
- Public query count = published indexable nodes (expect 221 stallions + any published container_home/article)
- **Critical:** Confirm published stallions without images are either indexed or explicitly accepted as search-excluded (currently 10 published imageless nids fail indexing)

### 5. Sitemap regeneration

```bash
ddev drush simple-sitemap:generate
# or
ddev drush cron
```

**Validate:** `/sitemap.xml` lists published stallion canonical URLs.

### 6. Cache rebuild

```bash
ddev drush cr
cd web/themes/custom/wcf_theme && npm ci && npm run build
ddev drush cr
```

### 7. Canonical URL governance (post-migration)

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

### 8. Validation checks

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

- `docs/canonical-url-governance.md`
- `docs/stallion-migration-rollback.md`
- `docs/staging-deployment-checklist.md`
- `docs/stallion-migration-validation.md`
