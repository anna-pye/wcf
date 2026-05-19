# Discovery Governance

**Date:** 2026-05-19  
**Branch:** `feature/legacy-classification-model`  
**Audience:** Editors, content strategists, developers  
**Related:** `docs/classification-domain-model.md`, `docs/discovery-architecture-audit.md`

---

## Purpose

This document defines **who owns what** in public discovery, how classification fields may be used, and what must not be duplicated as the platform scales.

---

## Route responsibilities

### `/stallions` — Stallion catalog (SQL View)

| Attribute | Value |
|-----------|-------|
| View | `stallions` (`page_1`) |
| Base | `node_field_data` |
| Bundles shown | `stallion` only |
| Filters | Exposed: `field_status`, `field_tags` (SQL) |
| Sort | `field_featured` DESC, `created` DESC |
| Row mode | `card` → `stallion-card` component |

**Responsibility:** Primary **horse-only** browsing. Editors and developers should treat this as the canonical stallion grid.

**Use when:**

- Browsing horses without cross-bundle noise
- Filtering by status on a simple SQL form
- Showing featured sort order

**Do not use for:**

- Finding container homes or articles
- Sitewide keyword search (use `/search`)

---

### `/search` — Sitewide discovery (Search API)

| Attribute | Value |
|-----------|-------|
| View | `search_stallions` (`page_1`) |
| Index | `stallion_content` |
| Bundles indexed | `stallion`, `container_home`, `article` |
| Keyword | Exposed fulltext `keywords` |
| Facets | `stallion_status`, `tags`, `content_type` |
| Facet blocks | Sidebar on `/search` only |

**Responsibility:** Cross-bundle discovery with faceted refinement.

**Use when:**

- Searching across content types
- Facet-driven exploration (status, type, tags)
- Keyword lookup

**Do not:**

- Add duplicate exposed SQL filters (removed intentionally per `wcf-discovery-setup.php`)
- Create a second Search API index for the same bundles
- Place facet blocks on `/stallions` (causes UX duplication)

---

## Classification governance

### Lifecycle (`field_status`)

| Rule | Detail |
|------|--------|
| Set on publish | Default `active` for new stallions |
| Mark sold | Set `sold` when transaction complete — do not unpublish solely for sold state |
| Retired | Use when horse withdrawn but not “sold” semantics |
| Do not | Create taxonomy terms for Active/Sold |

### Business tags (`field_tags`)

| Rule | Detail |
|------|--------|
| Allow-list | Maintain approved list in editorial handbook (≤20 terms) |
| Create terms | Restrict to users with `administer taxonomy` |
| Do not | Auto-tag from titles, categories, or bulk legacy import without workshop |
| Do not | Use tags for lifecycle (use `field_status`) |
| Do not | Use tags for “Featured” (use `field_featured`) |
| After bulk tag change | Run `ddev drush search-api:reset-tracker stallion_content` |

**Current state:** 0 stallions tagged; tags facet hidden on `/search`.

### Marketing (`field_featured`)

| Rule | Detail |
|------|--------|
| Purpose | Highlight on `/stallions` sort and featured block |
| Limit | Featured block display capped by paragraph config |
| Do not | Create “Featured” taxonomy term |
| Do not | Use for sold/active lifecycle |

### Homepage curation

| Mechanism | Governance |
|-----------|------------|
| `homepage` node + paragraphs | Marketing owns composition |
| `featured_stallions` paragraph | Explicit horse picks — not automatic from tags |
| `feature_card` paragraphs | Showcase-style cards — separate from taxonomy |

---

## Facet governance

| Facet | Governed by | Editor impact |
|-------|-------------|---------------|
| `stallion_status` | `field_status` values | Always visible when stallions indexed |
| `content_type` | Bundle machine names | Stable — do not add bundles without index update |
| `tags` | `field_tags` population | Hidden until terms exist on indexed content |

**Developers must not:**

- Add facets without index field + processor review
- Point facets at a second index
- Enable `auto_create` on tags without editorial policy (currently enabled — monitor closely)

**Facet scale:** See `docs/taxonomy-scale-readiness.md` — review when tag count &gt;25.

---

## Canonical URL policy

| Content | Pattern | Notes |
|---------|---------|-------|
| Stallion (new) | `/stallions/[node:title]` | Pathauto `node_stallion` |
| Stallion (migrated) | `/stallions/{legacy-alias}` | `pathauto: 0` in migration |
| Container home | `/container-homes/[title]` | Separate bundle |
| Page | `/[title]` | Agistment, static copy |

**Do not:**

- Recreate D7 `/category/{alias}` paths
- Add URL aliases that compete with `/stallions/{slug}`
- Bulk-change migrated aliases without redirect plan

---

## Editorial discovery rules

### Do

1. Set `field_status` accurately before promoting horses publicly.
2. Use `/stallions` to verify listing appearance after status changes.
3. Use `/search` to verify cross-bundle findability after major edits.
4. Request new tag terms through governance — do not invent during node save.
5. Use `field_featured` sparingly for homepage-quality highlights.

### Do not

1. Bulk-assign tags from legacy category names without approval.
2. Create parallel menus pointing to custom Views duplicating `/stallions`.
3. Unpublish sold horses that should remain in sold archive (use `field_status=sold`).
4. Tag container homes with horse section labels.
5. Use body text keywords as a substitute for structured classification.

---

## Developer rules (anti-duplication)

### Must not duplicate

| System | Single source |
|--------|---------------|
| Stallion listing View | `views.view.stallions` |
| Site search View | `views.view.search_stallions` |
| Search index | `search_api.index.stallion_content` |
| Status lifecycle | `field_status` on `stallion` |
| Horse canonical URLs | Pathauto + migration aliases |

### Before adding discovery features, answer:

1. Does `/stallions` or `/search` already solve this?
2. Can an exposed filter or facet be extended instead of a new View?
3. Will this create a second index or facet source?
4. Does classification belong in lifecycle, tags, or marketing — not two places?

### Acceptable future additions (gated)

| Addition | Condition |
|----------|-----------|
| Governed tag migration | Approved tag list + one migration plugin |
| `field_year` exposed filter | Product-owner sign-off |
| `node--container_home--card` in search | UX consistency — not classification |
| One normalization drush command | Documented, idempotent, entity API only |

---

## Future onboarding checklist

When onboarding a new horse section or business line:

- [ ] Audit D7 legacy (`docs/legacy-classification-audit.md` process)
- [ ] Confirm D11 bundle exists or justify new bundle
- [ ] Map to lifecycle / tags / marketing — not all three
- [ ] Update Search API index only if new bundle
- [ ] Update facet `content_type` only if indexed
- [ ] Document in this file if governance rules change
- [ ] Run validation: `drush cr`, `cim`, `cst`, `search-api:status`, route smoke test

---

## Validation commands (operational)

```bash
cd ~/drupal11-upgrade
ddev drush cr
ddev drush cim -y
ddev drush cst
ddev drush search-api:status
npm run build --prefix web/themes/custom/wcf_theme

# Route smoke (inside ddev)
ddev exec curl -s -o /dev/null -w "%{http_code}" https://wcf11.ddev.site/stallions
ddev exec curl -s -o /dev/null -w "%{http_code}" https://wcf11.ddev.site/search
```

---

## Risk register

| Risk | Mitigation |
|------|------------|
| Editors create unbounded tags (`auto_create`) | Allow-list; restrict taxonomy permissions |
| Developers add `/stallions-asb` duplicate View | Require architecture review; use filters |
| Legacy category import without cleanup | Blocked — see taxonomy normalization plan |
| Status facet shows wrong counts after bulk edit | Reindex tracker after bulk operations |
| Container home / stallion lifecycle confusion | Document `field_sold` vs `field_status` in editorial handbook |
