# Summary Remediation

**Date:** 2026-05-19  
**Script:** `scripts/remediate-empty-summaries.php`

## Current state (audit)

| Metric | Count |
|--------|------:|
| Stallion nodes | 276 |
| Empty `body` summary | 276 |
| Published stallions | 221 |

Metatag stallion defaults use `[node:summary]` for `description`, `og_description`, and Twitter cards — empty summaries weaken SEO and search excerpts until remediated.

## Approach

- Generate summary from **body text trim only** where summary is empty.
- **Never overwrite** a non-empty summary (manual editorial override preserved).
- No Twig hardcoding; no revision fork (`setNewRevision(FALSE)`).
- Default trim: 300 characters (`text_summary()` with node body format).

## Usage

```bash
# Dry run (counts + samples)
ddev drush php:script scripts/remediate-empty-summaries.php

# Apply for stallions
ddev drush php:script scripts/remediate-empty-summaries.php -- --apply

# Optional bundle / trim length
ddev drush php:script scripts/remediate-empty-summaries.php -- --apply --bundle=article --trim=250
```

## Rerun safety

| Scenario | Behavior |
|----------|----------|
| Re-run dry run | Same counts until summaries filled |
| Re-run `--apply` | Skips nodes that already have summaries — idempotent |
| Editor sets manual summary later | Preserved — script only touches empty summaries |

## Rollback

No automatic snapshot. To revert a node:

1. Edit node → clear summary field → save; or
2. Restore from revision before remediation batch.

For bulk rollback, restore database revision or re-migrate body from D7 source.

## Post-remediation

```bash
ddev drush cr
ddev drush search-api:reset-tracker stallion_content
ddev drush php:script scripts/wcf-search-integrity.php -- --apply --reindex
```

Reindex ensures `summary` index field and Metatag tokens reflect new text.
