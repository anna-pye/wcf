# Config Governance Audit

**Date:** 2026-05-19  
**Branch:** `feature/faceted-discovery`  
**Commands run:** `ddev drush cst`, `ddev drush cex -y` (then reverted unrelated drift), `ddev drush cr`

## Executive summary

Active configuration matches `config/sync` after cache rebuild and export. Discovery-related config is complete, dependency-linked, and free of duplicate Search API indexes or orphaned facet YAML. One incidental admin-theme reorder was detected during export and reverted.

## Config sync status

```
ddev drush cst
→ No differences between DB and sync directory.
```

**Post-audit state:** Clean (verified after `drush cr` + `cex` + revert of `gin.settings.yml` key reorder only).

## Discovery config inventory

### Search API

| Config | Status | Notes |
|--------|--------|-------|
| `search_api.settings.yml` | Exported | — |
| `search_api_db.settings.yml` | Exported | Database backend |
| `search_api.server.wcf_database.yml` | Exported, enabled | Single server |
| `search_api.index.stallion_content.yml` | Exported, enabled | Only WCF content index |

**Finding:** No duplicate `search_api.index.*` entities.

### Facets

| Config | Depends on |
|--------|------------|
| `facets.facet.stallion_status.yml` | index, `search_stallions` view |
| `facets.facet.categories.yml` | index, `search_stallions` view |
| `facets.facet.content_type.yml` | index, `search_stallions` view |
| `facets_summary.facets_summary.search_active_filters.yml` | index, `search_stallions` view |

**Finding:** All facet configs declare dependencies on `search_api.index.stallion_content` and `views.view.search_stallions`. No orphaned facet YAML in sync.

### Views (WCF-tagged)

| View | Exported | Enabled |
|------|----------|---------|
| `search_stallions` | Yes | Yes |
| `stallions` | Yes | Yes |
| `featured_content` | Yes | Yes |
| `content_health` | Yes | Yes |
| `media_governance` | Yes | Yes |

### Block placements (discovery)

| Block | Region | Theme | Visibility |
|-------|--------|-------|------------|
| `wcf_facet_stallion_status` | `sidebar_first` | `wcf_theme` | `/search`, `/search*` |
| `wcf_facet_categories` | `sidebar_first` | `wcf_theme` | `/search`, `/search*` |
| `wcf_facet_content_type` | `sidebar_first` | `wcf_theme` | `/search`, `/search*` |
| `wcf_search_facets_summary` | `content` | `wcf_theme` | `/search`, `/search*` |

**Finding:** No facet blocks placed on non-search routes. No stale Olivero/Claro facet blocks.

## Disabled-but-exported configs (expected)

Standard/core items with `status: false` in sync (not WCF defects):

- `views.view.archive.yml`, `views.view.glossary.yml`
- `simple_sitemap.sitemap.index.yml` (index sitemap definition; bundle sitemaps separate)
- Several unused core entity view modes

**Finding:** No disabled WCF discovery configs (`search_stallions`, facets, index) — all enabled.

## Module enablement

From `core.extension.yml` (discovery-relevant):

- `search_api`, `search_api_db`, `facets`, `facets_summary` — enabled
- `content_moderation`, `workflows` — enabled
- `wcf_migrate` — enabled (migration readiness)

## Dependency validation

- Facet blocks → `facets.facet.*` → index + view: **valid chain**
- `search_stallions` page display → `search_api.index.stallion_content`: **valid**
- Index field settings reference existing field storage (`field_category`, `field_status`, `body`, etc.): **valid**

## Incidental export drift (resolved)

`ddev drush cex -y` produced an update to `gin.settings.yml` consisting of YAML key reordering only (no functional value changes). **Reverted** to avoid unrelated PR noise.

**Recommendation:** On staging deploy, run `drush cst` before import; ignore Gin reorder-only diffs unless intentionally standardizing admin theme export.

## Stale config risk areas (monitor post-migration)

| Area | Risk | Mitigation |
|------|------|------------|
| Facet blocks after path change | Blocks invisible if `/search` path changes | Update `block.block.wcf_*` visibility |
| Index bundles | New bundles need explicit index + facet review | Update `stallion_content` datasource only via controlled change |
| Tag vocabulary growth | Facet block weight/UX | See `taxonomy-scale-readiness.md` |

## Governance checklist

- [x] All discovery config in `config/sync`
- [x] `drush cst` clean
- [x] Single Search API index for public discovery
- [x] Facet entities aligned with index + view
- [x] Block placements scoped to `/search`
- [x] No disabled WCF discovery exports
