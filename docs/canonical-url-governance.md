# Canonical URL Governance

**Date:** 2026-05-19  
**Pathauto pattern:** `/stallions/[node:title]` (`pathauto.pattern.node_stallion`)

## Root cause: legacy short URLs return HTTP 200

Drupal inbound path resolution order:

1. **path_alias** — if `/vegas` is an active alias for `/node/14`, the request resolves directly to the stallion node (HTTP 200).
2. **Redirect module** — only evaluated when no path alias matches the inbound path.

Both legacy short aliases and canonical `/stallions/*` aliases can be active for the same node. Redirect entities (301 from short → canonical) are correct, but **Redirect never runs** while the short alias row exists.

**Remediation:** create redirects first, then remove only the conflicting legacy `path_alias` rows via entity API after verification.

## Findings (audit baseline)

| Check | Result |
|-------|--------|
| Duplicate alias strings (same alias → multiple paths) | 0 |
| Published stallions with multiple active aliases | 8 |
| Legacy short aliases (not under `/stallions/`) | 7 |

### Legacy short alias examples

| Legacy alias | nid | Canonical `/stallions/*` alias |
|--------------|-----|--------------------------------|
| `/vegas` | 14 | `/stallions/clients-foal-tilly` |
| `/moonlark` | 16 | `/stallions/gold-gisele` |
| `/hagia-sophia` | 17 | `/stallions/maybe-mabelline` |
| `/greasy-heel-2` | 18 | *(paired `/stallions/…` alias)* |
| `/profile-in-style` | 25 | *(paired `/stallions/…` alias)* |
| `/thomas_the_tank` | 28 | *(paired `/stallions/…` alias)* |
| `/compliant_container_homes` | 31 | *(paired `/stallions/…` alias)* |

### Metatag

`metatag.metatag_defaults.node__stallion` sets:

- `canonical_url: '[node:url]'`
- `og_url: '[node:url:absolute]'`

Canonical follows the alias Drupal selects for `[node:url]` (typically Pathauto `/stallions/*`).

### Search API

Index processor `add_url` stores item URL at index time. After alias/redirect changes, **reindex** so indexed URLs match canonical paths.

## Safe governance model

1. **Canonical public URL:** `/stallions/{slug}` (Pathauto) — preserve canonical `path_alias` rows.
2. **Legacy inbound URLs:** 301 Redirect module entries from short alias → canonical `/stallions/*`.
3. **Alias cleanup:** remove legacy short `path_alias` rows only after redirect verified (second script).
4. **Do not** hardcode redirects in `.htaccess` or theme.
5. **Do not** disable path_alias or add custom inbound processors.

## Operational sequence

Run in order on staging (dry-run first):

### Step 1 — Create redirects (if missing)

```bash
# Plan redirects (no writes)
ddev drush php:script scripts/canonical-redirect-remediation.php

# Create 301 redirects
ddev drush php:script scripts/canonical-redirect-remediation.php -- --apply
```

### Step 2 — Remove conflicting legacy aliases

```bash
# Plan alias removals (no writes, default)
ddev drush php:script scripts/canonical-alias-governance.php

# Delete verified legacy path_alias rows only
ddev drush php:script scripts/canonical-alias-governance.php -- --apply
```

### Step 3 — Cache + HTTP validation

```bash
ddev drush cr
curl -sI https://wcf11.ddev.site/vegas
curl -sI https://wcf11.ddev.site/stallions/clients-foal-tilly
```

**Expected after Step 2 apply:**

| URL | Status | Notes |
|-----|--------|-------|
| `/vegas` | 301 | `Location: /stallions/clients-foal-tilly` (or absolute equivalent) |
| `/stallions/clients-foal-tilly` | 200 | Canonical alias unchanged |

### Step 4 — Search reindex (optional but recommended)

```bash
ddev drush php:script scripts/wcf-search-integrity.php -- --apply --reindex
```

## Script reference

| Script | Purpose |
|--------|---------|
| `scripts/canonical-redirect-remediation.php` | Create Redirect entities (legacy → canonical) |
| `scripts/canonical-alias-governance.php` | Remove legacy `path_alias` after redirect verified |

### Alias governance safety rules

Legacy aliases are **not** removed when:

- No canonical `/stallions/*` alias exists
- Redirect entity missing for the short path
- Redirect target does not match canonical (`internal:/stallions/…`)
- Multiple canonical aliases exist for the same node
- Alias path does not match `/node/{nid}` (non-stallion guard)

### JSON report fields (`canonical-alias-governance.php`)

- `published_stallions`
- `canonical_aliases_verified`
- `redirects_verified`
- `aliases_planned_for_removal`
- `aliases_removed`
- `aliases_skipped`
- `conflicts`
- `apply`
- `plan[]` — per item: `nid`, `removed_alias`, `canonical_alias`, `redirect_verified`, `status`

## Validation checklist

- [ ] Dry-run shows `aliases_planned_for_removal` > 0 only when redirects exist
- [ ] `conflicts` empty before apply
- [ ] Apply completes with `aliases_removed` matching plan
- [ ] Legacy URL returns 301 (not 200)
- [ ] Canonical `/stallions/*` returns 200
- [ ] Re-run dry-run: `aliases_planned_for_removal` = 0 (idempotent)
- [ ] Redirect entities still present in `/admin/config/search/redirect`
- [ ] `rel=canonical` on stallion pages points to `/stallions/*`

## Rollback guidance

If legacy URLs break after alias removal:

1. **Recreate legacy path_alias** (entity API or admin UI):
   - Path: `/node/{nid}`
   - Alias: legacy short path (e.g. `/vegas`)
   - Language: site default
2. Redirect entities remain — inbound may 301 before alias resolution depending on state; test both paths.
3. **Do not** delete canonical `/stallions/*` aliases during rollback.
4. Export nothing from rollback unless intentionally capturing restored aliases.

To restore full pre-remediation state:

```bash
# Re-run redirect remediation if redirects were removed
ddev drush php:script scripts/canonical-redirect-remediation.php -- --apply
# Manually recreate legacy path_alias rows for affected nids
ddev drush cr
```

## Unresolved legacy risks

- Container home / page bundles may have similar dual-alias patterns — separate audit if inbound legacy URLs exist.
- Underscore aliases (`/thomas_the_tank`) — consider editorial alias normalization via Pathauto regenerate (manual QA).
