# Stallion migration version-control audit

**Date:** 2026-05-20  
**Branch:** `feature/stallion-migration-version-control`  
**Base:** `main`  
**Legacy reference:** `~/drupal7-legacy`  
**D11 target:** `~/drupal11-upgrade`

## Executive summary

The stallion migration pipeline was implemented on `feature/stallion-migration` (commit `8b2ae87e`) but **never merged to `main`**. The local database already had 276 imported stallion nodes and Search API at 278/278; reproducibility from Git alone was blocked.

**Resolution:** Cherry-picked `8b2ae87e` onto `feature/stallion-migration-version-control` from `main`. All required YAML and plugin files are now version-controlled. Legacy product binaries remain **out of Git** (`.gitignore` already on `main`).

---

## Phase 1 — File existence checklist

| Asset | Expected path | In Git (after fix) | Notes |
|-------|---------------|-------------------|-------|
| `wcf_d7_file_product.yml` | `web/modules/custom/wcf_migrate/migrations/` | Yes | Restored from `8b2ae87e` |
| `wcf_d7_media_image_product.yml` | same | Yes | |
| `wcf_d7_media_document_product.yml` | same | Yes | |
| `wcf_d7_media_remote_video_product.yml` | same | Yes | |
| `wcf_d7_node_stallion.yml` | same | Yes | Uses `field_status`, not tags |
| `WcfD7Product.php` | `src/Plugin/migrate/source/` | Yes | `wcf_d7_product` source |
| `WcfD7ProductFile.php` | `src/Plugin/migrate/source/` | Yes | Product module filesystem |
| `WcfD7ProductPathAlias.php` | `src/Plugin/migrate/process/` | Yes | `/stallions/{slug}` |
| `WcfD7YoutubeEmbedUrl.php` | `src/Plugin/migrate/process/` | Yes | iframe → oEmbed URL |

### Supporting docs (referenced by runbooks)

| Document | On `main` before | After branch |
|----------|------------------|--------------|
| `docs/stallion-migration-audit.md` | Missing | Added |
| `docs/stallion-migration-validation.md` | Missing | Added |
| `docs/stallion-migration-rollback.md` | Missing | Added |
| `docs/stallion-migration-version-control-audit.md` | Missing | Added (this file) |
| `legacy/README.md` (product files rsync) | Missing | Added |

### Docs that reference stallions but are **not** stallion-migration config

| Document | Status |
|----------|--------|
| `docs/staging-rehearsal.md` | Updated (migration sequence) |
| `docs/legacy-custom-module-parity-audit.md` | Untracked locally — **not** in this commit |
| `docs/legacy-functionality-gap-report.md` | Untracked locally — **not** in this commit |
| `docs/legacy-custom-module-inventory.md` | Untracked locally — **not** in this commit |

---

## Phase 2 — Docs vs repository (before fix)

### What docs said existed

| Source | Claim |
|--------|-------|
| `docs/staging-rehearsal.md` | Rollback/import for `wcf_d7_node_stallion`, `wcf_d7_file_product`, product media migrations |
| `docs/legacy-custom-module-parity-audit.md` | Migration YAML **not in repo**; 278 indexed in DB |
| `web/modules/custom/wcf_migrate/README.md` (on `main`) | Stallion migration **not yet implemented** |

### What `main` actually contained

- `wcf_migrate` with page, container_home, taxonomy, D7 field-based file/media only
- No `wcf_d7_*_product*` migrations
- No `WcfD7Product*` plugins
- `.gitignore` entry for `legacy/sites/all/modules/product/files/` (from `773262fd`, already on `main`)

### What was missing from Git

All stallion pipeline YAML, four PHP plugins, three stallion docs, and `legacy/README.md` product rsync section.

### What was present locally but untracked

- Untracked parity/gap/inventory docs only (unrelated to migration YAML restore)

### What should **not** be committed

| Path / type | Reason |
|-------------|--------|
| `legacy/sites/all/modules/product/files/**` | Large binaries; rsync from D7 at deploy time |
| `~/drupal7-legacy/sites/all/modules/product/files/` | Legacy reference only |
| `vendor/`, `web/core`, contrib | Standard ignore |
| Generated theme CSS | Theme build policy — build on staging |

---

## Phase 3 — Restore actions taken

| Action | Source of truth |
|--------|-----------------|
| Cherry-pick `8b2ae87e` onto branch from `main` | Prior audited implementation on `feature/stallion-migration` |
| Skip cherry-pick `773262fd` | Empty on `main` — `.gitignore` already excludes product files |
| No new field mappings invented | Mappings unchanged from `8b2ae87e` |

### Intentional gaps (documented, not migrated)

| D7 source | D11 | Status |
|-----------|-----|--------|
| `category_id` / `wcf_category` | `field_category` | **Deferred** — editorial workshop required |
| `year` | — | **Deferred** |
| `field_tags` | — | No D7 source on `wcf_product` |

---

## Phase 4 — Validation results (2026-05-20, DDEV)

```text
git status --short
  (only untracked legacy audit docs; migration files committed on branch)

ddev drush cr
  [success] Cache rebuild complete.

ddev drush migrate:status (stallion pipeline)
  wcf_d7_file_product          Idle  1149 total  353 imported  796 unprocessed
  wcf_d7_media_image_product   Idle  1071 total  276 imported  795 unprocessed
  wcf_d7_media_document_product Idle 78 total   77 imported   1 unprocessed
  wcf_d7_media_remote_video_product Idle 5 total 4 imported  1 unprocessed
  wcf_d7_node_stallion         Idle  278 total  276 imported  0 unprocessed  2 messages

ddev drush migrate:messages wcf_d7_node_stallion
  2 failures: empty title (source ids 284, 285) — expected per audit

ddev drush cst
  [notice] No differences between DB and sync directory.

ddev drush search-api:status
  stallion_content  100%  278 indexed / 278 total
```

**Note:** Unprocessed file/media rows are predominantly missing source files on disk (796 file refs) — expected per `docs/stallion-migration-validation.md`. No destructive rollback/import was run for this audit.

---

## Phase 5 — Files added in this wave

### Version-controlled (commit)

- `web/modules/custom/wcf_migrate/migrations/wcf_d7_file_product.yml`
- `web/modules/custom/wcf_migrate/migrations/wcf_d7_media_image_product.yml`
- `web/modules/custom/wcf_migrate/migrations/wcf_d7_media_document_product.yml`
- `web/modules/custom/wcf_migrate/migrations/wcf_d7_media_remote_video_product.yml`
- `web/modules/custom/wcf_migrate/migrations/wcf_d7_node_stallion.yml`
- `web/modules/custom/wcf_migrate/src/Plugin/migrate/source/WcfD7Product.php`
- `web/modules/custom/wcf_migrate/src/Plugin/migrate/source/WcfD7ProductFile.php`
- `web/modules/custom/wcf_migrate/src/Plugin/migrate/process/WcfD7ProductPathAlias.php`
- `web/modules/custom/wcf_migrate/src/Plugin/migrate/process/WcfD7YoutubeEmbedUrl.php`
- `web/modules/custom/wcf_migrate/wcf_migrate.module` (plugin registration)
- `web/modules/custom/wcf_migrate/README.md`
- `docs/stallion-migration-audit.md`
- `docs/stallion-migration-validation.md`
- `docs/stallion-migration-rollback.md`
- `docs/stallion-migration-version-control-audit.md`
- `docs/staging-rehearsal.md`
- `legacy/README.md`

### Intentionally not added

- Product file binaries under `legacy/sites/all/modules/product/files/`
- Untracked legacy parity/gap/inventory reports (separate editorial audit work)

---

## Remaining risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Staging DB empty — must run full pipeline | High | Follow staging command sequence below |
| 796 file rows missing on disk | Medium | Rsync legacy files; accept unprocessed count |
| 2 stallions skipped (empty titles) | Low | Documented; ids 284–285 |
| `field_category` not populated by migration | Medium | Editorial mapping script when approved |
| 10 published stallions without images may be search-excluded | Medium | Images or index config — see validation doc |
| Branch not merged to `main` | Medium | PR + staging rehearsal before production |

---

## Exact staging command sequence

Prerequisites: DB snapshot, `wcf_migrate` enabled, legacy DB in DDEV, code on this branch.

```bash
# 1. Legacy product files (not in Git)
rsync -a ~/drupal7-legacy/sites/all/modules/product/files/ \
  ~/drupal11-upgrade/legacy/sites/all/modules/product/files/

# 2. Cache + migration import (dependency order)
ddev drush cr
ddev drush mim wcf_d7_file_product -y
ddev drush migrate:import wcf_d7_media_image_product --force -y
ddev drush migrate:import wcf_d7_media_document_product --force -y
ddev drush migrate:import wcf_d7_media_remote_video_product --force -y
ddev drush migrate:import wcf_d7_node_stallion --force -y

# 3. Validate migrations
ddev drush migrate:status --fields=id,status,total,imported,unprocessed,message_count | grep wcf_d7
ddev drush migrate:messages wcf_d7_node_stallion
ddev drush sql:query "SELECT COUNT(*) FROM node_field_data WHERE type='stallion';"

# 4. Search integrity + reindex
ddev drush php:script scripts/wcf-search-integrity.php
ddev drush php:script scripts/wcf-search-integrity.php -- --apply --reindex
ddev drush search-api:status

# 5. Sitemap
ddev drush simple-sitemap:generate

# 6. Cache (+ theme build if CSS changed)
ddev drush cr

# 7. Canonical URL governance (dry-run, then apply)
ddev drush php:script scripts/canonical-redirect-remediation.php
ddev drush php:script scripts/canonical-redirect-remediation.php -- --apply
ddev drush php:script scripts/canonical-alias-governance.php
ddev drush php:script scripts/canonical-alias-governance.php -- --apply
ddev drush cr

# 8. QA
# /stallions, /search, sample stallion detail, legacy short-alias 301s
ddev drush cst
```

**Clean re-run rollback** (destructive — staging only): see `docs/stallion-migration-rollback.md`.

---

## Recommendation

1. Merge `feature/stallion-migration-version-control` to `main` after PR review.
2. Run full staging rehearsal per `docs/staging-rehearsal.md` on a fresh DB snapshot.
3. Keep product files out of Git permanently; document rsync in deploy runbooks.
4. Track `field_category` assignment as a separate editorial task — do not block migration merge.
