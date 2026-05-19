# Moderation + Governance QA

**Date:** 2026-05-19  
**Branch:** `feature/editorial-normalization`  
**Workflow:** `editorial` on `stallion`, `article`, `homepage`

## Editorial workflow states

| Legacy D7 | D11 migration mapping | Verified |
|-----------|----------------------|----------|
| `status=1` | `published`, `status=1` | Yes |
| `status=0` | `draft`, `status=0` | Yes |
| Moderation mismatch (published↔draft) | 0 nodes | PASS |

## Draft exclusion (public)

| Surface | Unpublished stallions visible? |
|---------|-------------------------------|
| `/stallions` View | No (status filter) |
| `/search` | No (`entity_status` processor) |
| Direct node URL | No (403/404 for anonymous) |
| Search API tracker | Present but query-filtered |

**Unpublished leakage:** None detected.

## Moderation dashboard

- Workflow: `workflows.workflow.editorial.yml`
- Stallion bundle: new revisions enabled, moderation enabled
- **Re-validate** when draft/review content created in D11 (not only migrated publish/draft)

## Content health views

| Display | Path | Audit count |
|---------|------|-------------|
| Overview (unpublished) | `/admin/content/health` | 57 unpublished nodes (all types) |
| Missing images | `/admin/content/health/missing-images` | 10 published with missing main image |
| Stale content | `/admin/content/health/stale` | Requires manual filter review |

Aligns with editorial audit: 55 unpublished stallions + 11 missing images (10 published missing + overlap).

## Media governance views

- Existing admin View for media hygiene (see `views.view.media_governance` in config)
- Use after editorial image replacement to find orphans

## Revision handling

- `new_revision: true` on stallion type
- Migrated nodes: default revision matches published state
- **Recommendation:** Train editors to use moderation transitions, not direct publish checkbox bypass

## Revision log usability

- Standard Drupal log + moderation history on moderated bundles
- **Manual test:** Edit sample stallion → submit for review → verify log entries

## Migrated content governance

| Rule | Status |
|------|--------|
| Legacy unpublished remain unpublished | PASS |
| No `field_featured` abuse on drafts | PASS |
| `field_status` empty on 12 nodes | Needs editorial cleanup |

## Validation commands

```bash
ddev drush sql:query "SELECT COUNT(*) FROM node_field_data WHERE type='stallion' AND status=0;"
ddev drush php:script scripts/editorial-audit.php | jq '.issue_counts.moderation_mismatch'
```

## Admin manual checks

- [ ] `/admin/content/health` — filters work
- [ ] `/admin/content/health/missing-images` — lists 10 published gaps
- [ ] Moderation sidebar on stallion edit form
- [ ] Media governance view loads without PHP warnings
