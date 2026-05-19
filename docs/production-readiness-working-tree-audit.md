# Production Readiness — Working Tree Audit

**Date:** 2026-05-19  
**Repository:** `~/drupal11-upgrade`  
**Command:** `git status --short`

## Executive summary

The branch has **prior in-flight stabilization work** (theme card parity, search index, container_home teaser) plus **substantial untracked artifacts**. No merge-blocking git conflicts were detected. **Proceed with stabilization** after isolating unrelated untracked noise and **not** exporting `gin.settings.yml` drift.

**Action taken during this pass:** `config/sync/gin.settings.yml` was reverted to committed state (cosmetic key reorder only; rule: do not touch Gin admin theme config).

## Tracked modifications (10 files, pre-revert)

| Path | Nature | Risk | Recommendation |
|------|--------|------|----------------|
| `config/sync/block.block.wcf_search_facets_summary.yml` | Facet summary block tweak | Low | Include if intentional; verify `/search` |
| `config/sync/core.entity_view_display.node.container_home.teaser.yml` | Exposes `field_main_image` on teaser | Low | Keep; complements card view mode |
| `config/sync/gin.settings.yml` | Key reorder (no functional change) | **Exclude** | **Reverted** — do not export |
| `config/sync/search_api.index.stallion_content.yml` | `delete_on_fail: false` | Medium | Keep — prevents index item loss on imageless nodes |
| `web/themes/custom/wcf_theme/*` (card, theme, SCSS, CSS) | Stallion card + summary fallback | Low | Keep — production-readiness scope |
| `config/sync/block.block.wcf_theme_mainnavigation.yml` | Untracked new block | Low | Review before `cex` |
| `config/sync/search.page.content.yml` | Untracked core search page | Low | Confirm not duplicate of Search API view |

## Untracked — safe to commit (project artifacts)

| Category | Examples |
|----------|----------|
| Operational docs | `docs/canonical-url-governance.md`, `docs/discovery-*.md`, `docs/media-governance-audit.md`, … |
| Read-only scripts | `scripts/canonical-*.php`, `scripts/editorial-audit.php`, `scripts/media-audit.php`, … |
| Cursor skill | `.cursor/skills/` |

## Untracked — high risk / do not bulk-add

| Path | Risk | Recommendation |
|------|------|----------------|
| `web/core/` | Full Drupal core tree outside Composer vendor layout | **Do not commit** — verify `.gitignore`; likely accidental extract |
| `web/index.php`, `web/.htaccess`, `web/sites/`, … | Scaffold / local environment | Exclude unless repo intentionally tracks scaffold |
| `LICENSE.txt` (root) | Drupal core license at repo root | Exclude |
| `.ddev/` | Local DDEV config | Usually gitignored per team policy |
| `legacy/sites/` | Legacy file mirror | Track only if deployment requires; large binary risk |

## Config drift assessment

| Item | Status |
|------|--------|
| Gin settings | Reverted — no export |
| Search API `delete_on_fail` | Intentional stabilization (imageless published nodes) |
| Container home teaser | Aligns with card media pipeline |
| New blocks / search.page | Untracked — reconcile before production `drush cim` |

## Dangerous drift check

| Check | Result |
|-------|--------|
| Git merge conflicts | None |
| Canonical redirect script removal | Not observed |
| Migration config deletion | Not observed |
| `gin.settings.yml` functional change | None (reverted) |
| Entire `web/core/` untracked | **Flag** — environment hygiene, not architecture |

## Stop / go

**GO** — continue stabilization pass.  
**STOP before production deploy** if `web/core/` or unrelated scaffold is staged; run `git clean` / `.gitignore` review first.

## Recommended pre-commit hygiene

1. `git checkout -- config/sync/gin.settings.yml` (done)
2. Confirm `.gitignore` covers `web/core/`, `.ddev/`
3. Stage only paths from this stabilization pass + intentional prior theme/search work
4. Run Phase 7 validation checklist before merge

## Related

- `docs/production-deployment-runbook.md` (created this pass)
- `docs/staging-rehearsal.md` (existing migration/search sequence)
