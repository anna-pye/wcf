# Search + SEO Remediation

**Date:** 2026-05-19  
**Environment:** DDEV (`wcf11.ddev.site`)  
**Branch (intended):** `feature/search-seo-remediation` — blocked at start by dirty working tree (see §8)

## 1. Root cause findings

### Search API — imageless stallions excluded from `/search`

| Finding | Detail |
|---------|--------|
| **Symptom** | 10 published stallions with empty `field_main_image` were not discoverable on `/search` while visible on `/stallions` |
| **Correlation** | All 10 lacked main image — **not causal** |
| **Mechanism** | `delete_on_fail: true` on `search_api.index.stallion_content` removed tracker rows when batch indexing failed to load items; `search-api:reset-tracker` does not re-insert deleted tracker rows |
| **Not the cause** | Image field on index, Views filters, Twig `{% if image %}`, `entity_status` / `content_access` processors, datasource `getItemIds()` |

**Evidence:** `docs/search-index-integrity-audit.md`, `scripts/wcf-search-integrity.php`

### Canonical URL / SEO duplication

| Finding | Detail |
|---------|--------|
| **Symptom** | Legacy short paths (e.g. `/vegas`) returned HTTP 200 alongside `/stallions/*` |
| **Mechanism** | Active `path_alias` rows resolve before Redirect module; both alias types could be active per node |
| **Remediation state** | Legacy short aliases removed from `path_alias`; Redirect entities serve 301 to `/stallions/*` |

### Redirect remediation script failure

| Finding | Detail |
|---------|--------|
| **Symptom** | `Redirect::create([...])` with ad-hoc `language` / `langcode` keys caused NULL language / SQL integrity errors |
| **Fix** | Use Redirect entity API: `setSource()`, `setRedirect()`, `setStatusCode()`, `setLanguage($default_langcode)`, `setPublished()` |

### Empty summaries (SEO / social)

| Finding | Detail |
|---------|--------|
| **Symptom** | Metatag defaults use `[node:summary]`; most stallions have empty body summary |
| **Impact** | Empty `description`, `og_description`, `twitter_cards_description` at render |
| **Display** | Card view mode already uses `text_summary_or_trimmed` (120 chars) for rendered body |

## 2. Search integrity remediation

### Config

- `config/sync/search_api.index.stallion_content.yml` — `delete_on_fail: false` (prevents permanent tracker orphaning)

### Operational tooling

- `scripts/wcf-search-integrity.php` — detects datasource/tracker drift, `rebuildTracker()` + optional reindex

```bash
# Dry run
ddev drush php:script scripts/wcf-search-integrity.php

# Apply tracker rebuild + reindex
ddev drush php:script scripts/wcf-search-integrity.php -- --apply --reindex
```

### Theme

- `stallion-card--no-media` modifier when `field_main_image` is empty (layout stability on `/search` and `/stallions`)

### Post-remediation validation (2026-05-19)

| Check | Result |
|-------|--------|
| Tracker items | 278 / 278 |
| Public published stallion query | 221 |
| Imageless sample nids (14, 16, …, 31) in backend index | Yes |
| `search-api:index stallion_content` | 278 items indexed |

## 3. Redirect remediation

### Script

`scripts/canonical-redirect-remediation.php`

- Resolves default site language (`en`)
- Uses `RedirectRepository::findMatchingRedirect()` with normalized source path (no leading slash)
- Creates redirects via entity API with explicit `setLanguage()`
- Preserves dry-run (`--apply` required for writes)
- Reports `errors[]` on failure (no silent drops)

### Current environment

- Dry-run: `redirects_planned: 0` — dual `path_alias` rows already resolved; redirects exist
- HTTP: `/vegas` → **301** → `/stallions/clients-foal-tilly`

### Alias cleanup (step 2)

`scripts/canonical-alias-governance.php` — removes legacy `path_alias` only when redirect verified. Dry-run: **0 planned** (legacy aliases already removed).

## 4. Canonical URL governance

| Rule | Implementation |
|------|----------------|
| Canonical path | `/stallions/{slug}` via Pathauto `pathauto.pattern.node_stallion` |
| Legacy inbound | 301 Redirect module → canonical internal path |
| No hardcoded `.htaccess` redirects | — |
| Search indexed URL | `add_url` processor — reindex after alias changes |
| Metatag canonical | `[node:url]` on `metatag.metatag_defaults.node__stallion` |

### Validation

```bash
curl -sI https://wcf11.ddev.site/vegas          # expect 301
curl -sI https://wcf11.ddev.site/stallions/clients-foal-tilly  # expect 200
ddev drush simple-sitemap:generate   # optional; confirm sitemap lists /stallions/* only
```

## 5. Summary fallback strategy

**Policy:** No bulk write to `body.summary`. Editorial control preserved.

### Runtime fallback

| Layer | Implementation |
|-------|----------------|
| Theme helper | `wcf_theme_stallion_summary_fallback()` in `wcf_theme.theme` |
| Metatag | `hook_metatags_alter()` — fills `description`, `og_description`, `twitter_cards_description` when summary empty and body exists |
| Cards | `stallion_summary_fallback` preprocess variable; card/teaser templates pass to component |
| Logic | Use stored summary if present; else `text_summary()` from body (300 chars metatag, 120 chars card preprocess) |

`scripts/remediate-empty-summaries.php` remains available for **optional** editorial batch fill — not required for SEO display.

## 6. Duplicate-title governance

**Audit:** `scripts/editorial-audit.php` — **26 duplicate title groups** among 276 stallion nodes.

| Guidance | Detail |
|----------|--------|
| **Do not auto-rename** | Many duplicates are legitimate (year variants, client foal naming patterns) |
| **Disambiguation** | Pathauto `/stallions/{title}` already produces unique slugs in most cases |
| **Search impact** | Fulltext uses title + body; duplicate titles may rank similarly — editorial disambiguation in title field is optional |
| **Workflow** | Editors may add year/status to title when creating new listings; review groups via audit JSON `duplicate_titles` |

## 7. Remaining deferred risks

| Risk | Severity | Action |
|------|----------|--------|
| 11 stallions still without main image | Medium | Editorial media workflow |
| 26 duplicate title groups | Low | Editorial review, no automation |
| 5 placeholder body patterns | Low | Manual content cleanup |
| `field_featured` unused on nodes | Low | Document; homepage uses Views/paragraphs |
| Container home / page dual-alias patterns | Low | Separate audit if legacy URLs exist |
| Production rollout | — | Export config; run integrity script + reindex on staging first |

## 8. Production rollout guidance

1. **Ensure clean git tree**, then branch: `git checkout -b feature/search-seo-remediation`
2. Import config: `drush cim -y` (confirm `delete_on_fail: false` on `stallion_content`)
3. Deploy theme + scripts
4. `drush cr`
5. `drush php:script scripts/canonical-redirect-remediation.php` (dry-run)
6. `drush php:script scripts/canonical-redirect-remediation.php -- --apply` (if plan non-empty)
7. `drush php:script scripts/canonical-alias-governance.php -- --apply` (only after redirects verified)
8. `drush php:script scripts/wcf-search-integrity.php -- --apply --reindex`
9. `drush simple-sitemap:generate`
10. Spot-check `/search`, `/vegas`, `/stallions/*`, homepage, metatag output

## 9. Files changed (this remediation)

| File | Change |
|------|--------|
| `config/sync/search_api.index.stallion_content.yml` | `delete_on_fail: false` |
| `scripts/canonical-redirect-remediation.php` | Redirect entity API + language handling |
| `scripts/wcf-search-integrity.php` | Tracker rebuild tooling (existing) |
| `web/themes/custom/wcf_theme/wcf_theme.theme` | Summary fallback + `hook_metatags_alter()` |
| `web/themes/custom/wcf_theme/templates/components/stallion-card.html.twig` | `summary_fallback`, `--no-media` |
| `web/themes/custom/wcf_theme/templates/node/node--stallion--card.html.twig` | Pass fallback |
| `web/themes/custom/wcf_theme/templates/node/node--stallion--teaser.html.twig` | Pass fallback |
| `docs/search-seo-remediation.md` | This document |

## 10. Related docs

- `docs/search-index-integrity-audit.md`
- `docs/canonical-url-governance.md`
- `docs/discovery-qa.md`
- `docs/seo-validation.md`
