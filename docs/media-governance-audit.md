# Media Governance Audit

**Date:** 2026-05-19  
**Branch:** `feature/editorial-normalization`  
**Scope:** Migrated stallion `field_main_image` + linked media/files  
**Method:** `ddev drush php:script scripts/media-audit.php`

## Summary

| Issue | Count | Severity |
|-------|------:|----------|
| Missing main image | 11 | High |
| Broken media reference | 0 | — |
| Missing alt text | 0 | — |
| Low resolution (&lt;400px width or height) | 265 | High (editorial) |
| Duplicate file hash (shared binary) | 6 groups | Medium |
| Oversized (&gt;2MB) | 0 | — |
| Thumb filename pattern on main image | 0 | — |

**Stallions with main image:** 265 / 276

## Missing originals / thumb fallbacks

Migration imported readable files from `legacy/sites/all/modules/product/files/` with thumb fallback when originals absent (~873 legacy filenames skipped per migration validation).

**Current state:** 265 main images are **350px wide** derivatives (legacy thumb pipeline). Filenames do not include `thumb_` prefix after copy rename, but dimensions confirm thumb-tier assets.

| Action | Owner |
|--------|-------|
| Replace with full-resolution source where available | Editorial / asset archive |
| Accept soft listing quality until replaced | Product owner sign-off |

**Missing main image nids:** `14, 16, 17, 18, 21, 24, 25, 27, 28, 31, 37`

## Duplicate media (binary-identical files)

6 hash collision groups (different `mid`, same SHA-256). Examples:

| nids | filenames |
|------|-----------|
| 101, 108 | `818380Web.jpg`, `936713Web.jpg` |
| 144, 180 | `318245_C5J4295.jpg`, `433428_C5J4295.jpg` |
| 147, 148 | `189714_C5J0446.jpg`, `951998_C5J0446.jpg` |
| 171, 182 | `463428_C5J4146.jpg`, `47162_C5J4146.jpg` |
| 207, 208 | `805119_C5J8107.jpg`, `376743_C5J8107.jpg` |
| 312, 313 | `705448_C5J0106.jpg`, `32856_C5J0106.jpg` |

| Action type | Recommendation |
|-------------|----------------|
| **Manual** | Confirm intentional reuse vs migration duplicate; consolidate media refs if same horse |
| **Safe automated** | None (do not delete without editorial sign-off) |

## Broken media refs

0 — all referenced files exist on disk.

## Missing alt text

0 — alt populated on audited `field_media_image` (likely from filename/title migration defaults). **Manual review** still recommended for descriptive accessibility copy.

## Oversized assets

0 files &gt; 2MB on main images.

## DO NOT (confirmed)

- Regenerate images artificially
- Create fake alt text
- Duplicate media entities

## Editorial replacement workflow

1. Source high-res from legacy archive or photographer.
2. Upload via Media Library → replace `field_main_image` on stallion node.
3. Clear caches: `ddev drush cr` (tag invalidation).
4. Optional: remove orphaned `mid` via Media governance view after reference check.

## Media cleanup workflow

1. Admin → Media governance view (existing).
2. Filter unreferenced media **after** node deletes/rollback.
3. Never bulk-delete `public://wcf_product/` files while stallion nodes reference them.

## Future image standards (recommendations)

| Standard | Value |
|----------|-------|
| Listing minimum | 1200px wide, JPEG/WebP, &lt;500KB |
| Hero/full | 2000px max width |
| Aspect | 4:3 for listings (matches `stallion-card`) |
| Alt text | Horse name + role (e.g. “Bay ASB filly, 2024”) |
| Originals | Retain in DAM/archive; Drupal stores web derivatives |

## Re-run audit

```bash
ddev drush php:script scripts/media-audit.php | jq '.issue_counts'
```
