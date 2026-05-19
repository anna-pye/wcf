# WCF Platform Performance Audit

**Date:** 2026-05-19  
**Scope:** Discovery layer implementation (facets, search UX, governance views)  
**Approach:** Observational audit — no premature optimization applied.

## Executive summary

The WCF Drupal 11 stack is appropriately cache-oriented for public discovery. Search uses Search API tag caching; Views listings use tag caching; Facets inherit Search API cache metadata. No architectural replacements are recommended at this stage.

## Responsive image payloads

| Area | Finding | Risk | Recommendation |
|------|---------|------|----------------|
| Stallion cards | `responsive_image.styles.card` (mobile + desktop) | Low | Continue using `card` view mode; avoid adding full-width sources to listings |
| Stallion full | Hero + full displays use dedicated styles | Medium on hero | Audit hero image upload dimensions editorially (>2400px width rarely needed) |
| Container/article teasers | Teaser view modes should not load hero styles | Low | Verify teaser displays do not reference hero image styles |

**Action:** Enforce editorial upload guidelines (max 2000px wide for listing images) in training — no code change required now.

## CSS and libraries

| Item | Finding | Risk | Recommendation |
|------|---------|------|----------------|
| Theme CSS | Single compiled `css/style.css` from SCSS | Low | Maintain one theme library (`wcf_theme/global`) |
| `site-search` library | Declares only `global` dependency — no duplicate CSS | Low | Correct pattern |
| `hero-slider` | Loaded only on homepage hero field | Low | No change |
| Admin (Gin) | Separate admin theme — no bleed to frontend | Low | Expected |

**Action:** None — no duplicate frontend CSS detected.

## Views cacheability

| View | Cache plugin | Contexts | Notes |
|------|--------------|----------|-------|
| `search_stallions` | `search_api_tag` | URL query args | Correct for faceted search |
| `stallions` | `tag` | URL query args | Correct |
| `featured_content` | `tag` | Minimal | Block renders cache per display + filters |
| `content_health` | `tag` | Admin only | Acceptable |
| `media_governance` | `tag` | Admin only | Acceptable |

**Action:** After content publishes, rely on tag invalidation; run `drush cr` only when deploying config.

## Search API query efficiency

| Item | Finding | Risk | Recommendation |
|------|---------|------|----------------|
| Index `stallion_content` | 3 bundles, focused fields, boost on title | Low | Good |
| Facets | Query only on `/search` via facet source | Low | Do not add facets to other pages without index review |
| Fulltext | `min_chars` 3 on server | Low | Aligns with tokenizer minimum word size |
| Processors | html_filter, ignorecase, transliteration on query | Low | Standard Search API stack |

**Action:** Monitor index size quarterly; reindex after bulk migrations only.

## Facets-specific performance

- Facet blocks use URL processor (query string) — cacheable per URL combination.
- Facet counts require additional aggregation queries on each search page load — acceptable at current content volume.
- **Watch:** If index exceeds ~10k items, evaluate facet widget `soft_limit` and hard limits.

## Items explicitly not changed

- No Varnish/CDN configuration in this pass
- No database index tuning
- No Search API backend swap (database backend appropriate for current scale)
- No lazy-loading changes to listing images (already `lazy` on media card display)

## Next observability steps (future phase)

1. Enable query logging in staging for `/search` with multiple facets active; capture slow queries.
2. Add Real User Monitoring (RUM) for LCP on `/search` and `/stallions` after launch traffic exists.
3. Review `simple_sitemap` cron duration if bundle count grows significantly.
