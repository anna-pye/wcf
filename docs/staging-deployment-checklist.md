# Staging Deployment Checklist

**Date:** 2026-05-19  
**Branch:** `feature/faceted-discovery` (do **not** merge directly to `main`)  
**Target:** Staging environment for controlled legacy ingestion and editorial testing

## Pre-deploy

- [ ] Merge PR to staging branch per team process (not direct to `main`)
- [ ] Review `docs/discovery-architecture-audit.md` with team
- [ ] Confirm staging database backup exists
- [ ] Confirm Composer lock matches local (`composer install --no-dev` on staging if applicable)

## Deploy code and config

```bash
# On staging app server or CI job
git fetch && git checkout <staging-branch>
composer install --no-interaction --prefer-dist
drush updatedb -y
drush config:import -y
drush cr
```

- [ ] `drush cst` reports no unexpected diffs (or only approved deltas)
- [ ] `drush pm:list --status=enabled` includes `search_api`, `search_api_db`, `facets`, `facets_summary`

## Theme assets

```bash
cd web/themes/custom/wcf_theme
npm ci
npm run build
drush cr
```

- [ ] `css/style.css` timestamp updated on staging

## Search API reindex

Required after config import, migration imports, or bulk content changes.

```bash
drush search-api:reset-tracker stallion_content
drush search-api:index stallion_content
drush search-api:status
```

Expected: `% Complete` = 100%, indexed count matches published indexable nodes.

- [ ] Visit `/search` — results and facet counts match content
- [ ] Unpublished nodes do not appear in public search

## Cache rebuild

```bash
drush cr
```

When to run:

- After config import
- After theme build
- After Search API full reindex
- After block placement changes

Optional (if render cache stale):

```bash
drush cache:rebuild
# Same as drush cr
```

## Sitemap regeneration

```bash
drush simple-sitemap:generate
# Or rely on cron if configured:
drush cron
```

- [ ] Verify `sitemap.xml` includes published stallions, container homes, articles
- [ ] Confirm unpublished/draft URLs absent

## Migration reindex workflow

After `wcf_migrate` or controlled D7 ingestion batch:

1. Complete migration batch
2. `drush search-api:reset-tracker stallion_content`
3. `drush search-api:index stallion_content`
4. `drush cr`
5. `drush simple-sitemap:generate`
6. Spot-check `/search`, `/stallions`, homepage paragraphs
7. Run content health Views as editor

```bash
# Optional: limit index during very large imports
drush search-api:index stallion_content --limit=500
# Repeat until status shows 100%
```

## Post-deploy smoke tests

| URL | Expected |
|-----|----------|
| `/` | 200, homepage renders |
| `/stallions` | 200, card grid |
| `/search` | 200, sidebar facets, keyword form |
| `/search?f[0]=content_type:stallion` | Filtered results + clear filters link |
| `/admin/content/health` | 200 for privileged user |
| `/admin/content/media/missing-alt` | 200 for privileged user |

## Rollback notes

- Restore DB backup if config import fails mid-way
- Previous Search API tracker state is lost after `reset-tracker` — reindex required after rollback unless DB restored

## Documentation references

- Architecture: `docs/discovery-architecture-audit.md`
- Config: `docs/config-governance-audit.md`
- Search QA: `docs/search-validation.md`
- Performance: `docs/platform-performance-audit.md`
- Taxonomy: `docs/taxonomy-scale-readiness.md`
- Editorial: `docs/editorial-ux-validation.md`
