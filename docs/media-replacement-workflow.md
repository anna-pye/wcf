# Media Replacement Workflow

**Scope:** Editorial replacement of existing `field_main_image` media on stallions and container homes.  
**Out of scope:** Bulk migration re-run, automated file pipeline changes, guessing missing legacy files.

## Source image standards

| Criterion | Requirement |
|-----------|-------------|
| Format | JPEG or WebP preferred; PNG only when transparency required |
| Minimum dimensions | **1200×900 px** (4:3) for hero-quality; **800×600 px** absolute minimum for listings |
| Aspect ratio | 4:3 preferred (matches card `aspect-ratio`) |
| Color space | sRGB |
| File size | Target &lt; 500 KB after export; hard cap 2 MB |
| Quality | No upscaled thumbs; source must be original photography |

## Recommended dimensions (Drupal image styles)

| Use | Style context | Notes |
|-----|---------------|-------|
| Card / search grid | `media.card` responsive style | 4:3 crop |
| Node hero / full | Bundle full display | Higher width variant |
| Social / OG | Metatag image tags | Min 1200 px wide when possible |

Confirm active styles in **Configuration → Media** and theme `responsive_image` config before replacing.

## Naming conventions

- Pattern: `{bundle}-{nid}-{slug}-main.jpg` (example: `stallion-144-redoute-star-main.jpg`)
- Lowercase, hyphens only, no spaces
- Avoid legacy `thumb_` prefixes on production assets
- Do not reuse filenames across different horses/properties unless binary-identical (see `docs/media-governance-audit.md`)

## Replacement workflow (single node)

1. **Locate node** — use `scripts/missing-image-report.php` or Content Health view.
2. **Source asset** — obtain full-resolution file from archive; verify dimensions.
3. **Create or replace media** — Media → Image; upload file; set **descriptive alt text** (horse name + context, not filename).
4. **Attach to node** — edit node `field_main_image`; save as draft if moderation applies.
5. **Review** — card view (`/stallions`, `/search`), full node, metatags preview.
6. **Publish** — transition moderation to Published.
7. **Reindex** — `ddev drush search-api:index stallion_content` (or wait for cron).
8. **Cache** — `ddev drush cr` if listing does not update.

## Rollback workflow

1. Revert node revision (Content → Revisions) **or** re-attach previous media entity.
2. Do not delete old media until rollback window closes (7 days editorial policy).
3. `ddev drush search-api:index stallion_content`
4. `ddev drush cr`

## Alt text standards

| Do | Don't |
|----|-------|
| Describe subject and action ("Bay stallion Redoute Star in paddock") | Filename or "image of horse" |
| Match visible crop | Keyword stuffing |
| Update when image changes | Leave empty |

## Governance references

- `docs/media-governance-audit.md` — current low-res / duplicate inventory
- `scripts/media-audit.php` — JSON audit (stallions)
- `scripts/missing-image-report.php` — markdown/CSV missing image list

## Do not

- Bulk replace without editorial sign-off per node
- Regenerate migrations to fix one-off assets
- Write directly to summary fields as part of media work
- Delete shared media still referenced by other nodes
