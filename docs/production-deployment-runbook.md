# Production Deployment Runbook

**Platform:** WCF Drupal 11  
**Type:** Stabilization deploy — config + theme + docs; no migration rewrite.

## Preconditions

- [ ] Database backup (snapshot + verified restore test on staging)
- [ ] Code tag or release branch merged; working tree clean of `web/core/` scaffold noise
- [ ] `ddev drush cst` clean on staging (except documented env deltas)
- [ ] Legacy product files rsynced if migration re-run planned (see step 1)

## 1. Rsync order (legacy files only)

When re-importing product media:

```bash
rsync -a ~/drupal7-legacy/sites/all/modules/product/files/ \
  ~/drupal11-upgrade/legacy/sites/all/modules/product/files/
```

**Validate:** file count aligns with migration audit; no destructive `--delete` on production `files/` without backup.

## 2. Code deploy

1. Deploy application code (Composer vendor, `web/modules/custom`, `web/themes/custom`, `config/sync`).
2. **Do not** deploy untracked `web/core/` tree from local working copy.
3. `composer install --no-dev --optimize-autoloader` (production host).

## 3. Config import sequence

```bash
drush updatedb -y
drush config:import -y
drush cr
```

**Exclude from export:** `gin.settings.yml` (environment-specific admin theme).

**Validate:** `drush config:status` — no pending changes except known env overrides.

## 4. Theme build

```bash
cd web/themes/custom/wcf_theme
npm ci
npm run build
cd -
drush cr
```

## 5. Search API reindex order

After bulk content or config affecting index fields:

```bash
drush search-api:reset-tracker stallion_content
drush search-api:index stallion_content
drush search-api:status
```

**Note:** Index uses `delete_on_fail: false` so imageless published nodes remain tracked; fix images editorially.

## 6. Sitemap generation

```bash
drush simple-sitemap:generate
# or
drush cron
```

**Validate:** `/sitemap.xml` includes published stallion, container_home, article canonical URLs.

## 7. Canonical URL governance (post-migration only)

Do **not** alter validated redirect logic without governance review.

```bash
drush php:script scripts/canonical-redirect-remediation.php
drush php:script scripts/canonical-redirect-remediation.php -- --apply
drush php:script scripts/canonical-alias-governance.php
drush php:script scripts/canonical-alias-governance.php -- --apply
drush cr
```

See `docs/canonical-url-governance.md`.

## 8. Cache rebuild order

```bash
drush cr
# after theme asset deploy
drush cr
```

Opcache / CDN purge per hosting provider after deploy.

## 9. Migration rerun sequence (optional — staging only)

Reverse order for rollback; forward order for import. See `docs/staging-rehearsal.md`:

```bash
drush mr wcf_d7_node_stallion -y
# … media migrations …
drush mim wcf_d7_file_product -y
drush migrate:import wcf_d7_media_image_product --force -y
# …
drush migrate:import wcf_d7_node_stallion --force -y
```

Then repeat Search API + canonical steps.

## 10. Validation checklist

| Check | Command / URL |
|-------|----------------|
| Config sync | `drush cst` |
| DB updates | `drush updatedb` |
| Homepage | `/` → 200 |
| Stallions | `/stallions` → 200 |
| Search | `/search` → 200 |
| Facets | Filter counts update |
| Card parity | Container homes on `/search` use card grid |
| Unpublished leak | Anonymous search has no draft titles |
| Editorial reports | `drush php:script scripts/editorial-summary-report.php` |
| Missing images | `drush php:script scripts/missing-image-report.php` |
| Redirect sample | Legacy short URL → 301 → canonical |
| Sitemap | `/sitemap.xml` |

## Rollback sequence

1. Restore database snapshot.
2. Redeploy previous code tag.
3. `drush config:import -y` (from previous release `config/sync` if needed).
4. `drush cr`
5. `drush search-api:reset-tracker stallion_content && drush search-api:index stallion_content`
6. Verify `/`, `/stallions`, `/search` → 200.

Do **not** run destructive `migrate:rollback` on production without explicit approval.

## Related docs

- `docs/production-readiness-working-tree-audit.md`
- `docs/staging-rehearsal.md`
- `docs/media-replacement-workflow.md`
- `docs/final-accessibility-qa.md`
