# Editorial UX Validation

**Date:** 2026-05-19  
**Environment:** DDEV (`wcf11.ddev.site`)  
**Method:** Route verification, config review, rendered HTML inspection, form_alter evidence

## Summary

Discovery and governance surfaces are operational. Editor-facing clarity is good for moderation hints and search empty states. A few inconsistencies (mixed search card UX, facet label casing, low tag vocabulary use) should be addressed through **editorial training** and post-migration content—not platform redesign.

## Homepage composition

| Check | Route | Result |
|-------|-------|--------|
| Front page loads | `/` | HTTP 200 |
| Homepage node type | `homepage` bundle | Config + paragraphs present |
| Hero slider library | `field_hero_slides` | Attached via preprocess (evidence: `wcf_theme.theme`) |
| Global styles | `wcf_theme/global` | Attached on homepage preprocess |

**Finding:** Homepage composition architecture intact. Full visual QA with migrated hero assets recommended post-ingestion.

## Featured content paragraph

| Check | Evidence | Result |
|-------|----------|--------|
| Preprocess builds View | `preprocess_paragraph__featured_content` | Uses `featured_content` View by `field_content_type` |
| Limit bounds | 1–12 items | Enforced in preprocess |
| Template | `paragraph--featured-content.html.twig` → component | Present |
| View displays | `block_article`, `block_container_home`, etc. | In `views.view.featured_content.yml` |

**Verdict:** **PASS** — paragraph-driven, View-backed pattern matches D11 architecture.

## Featured stallions paragraph

| Check | Evidence | Result |
|-------|----------|--------|
| Preprocess | `preprocess_paragraph__featured_stallions` | `stallions` View `block_featured` |
| Count bounds | 1–12 | Enforced |
| Template | `paragraph--featured-stallions.html.twig` | Present |
| View filter | `field_featured = 1` | In `views.view.stallions.yml` block_featured |

**Verdict:** **PASS**

## Moderation workflows

| Check | Evidence | Result |
|-------|----------|--------|
| Workflow | `editorial` on article, homepage, stallion | Config present |
| Form hints | `form_alter` on node forms | Draft / In review / Published descriptions |
| Moderated content View | `views.view.moderated_content` | Exported |

**Verdict:** **PASS** for configuration. Re-test with draft/review fixtures after migration.

## Content health views

| Display | Path | Anonymous access |
|---------|------|------------------|
| Overview | `/admin/content/health` | 403 (expected) |
| Missing images | `/admin/content/health/missing-images` | Not tested (requires login) |
| Stale content | `/admin/content/health/stale` | Not tested |

**Verdict:** Routes exist; access restricted — **PASS** for hard access control pattern.

## Media governance views

| Display | Path |
|---------|------|
| Missing alt | `/admin/content/media/missing-alt` |
| Oversized | `/admin/content/media/oversized` |
| Unused | `/admin/content/media/unused` |

Anonymous: HTTP 403 — **PASS**

## Search discoverability

| Check | Result |
|-------|--------|
| `/search` in main menu | Config: `search_stallions` page_1 menu weight 10 |
| Page title / meta | “Search” rendered |
| Keyword form labels | “Keywords” + description |
| Result count header | “Displaying 1–2 of 2 results” |
| Empty state message | Configured with link to clear filters |
| Facets summary | “Active filters” + clear link when filtered |

**Verdict:** **PASS**

## Stallions listing (`/stallions`)

| Check | Result |
|-------|--------|
| Page loads | HTTP 200 |
| Semantic layout | `stallion-listing-page` component with h1 |
| Exposed filters | Status select (Active/Sold/Retired), Tag select |
| Card grid | `stallion-card` component |

**Verdict:** **PASS**

## Editor clarity issues (non-blocking)

| Issue | Evidence | Recommendation |
|-------|----------|----------------|
| Two discovery URLs | `/stallions` vs `/search` | Onboarding doc |
| Search mixed card/teaser | container_home teaser on `/search` | Post-migration display alignment |
| Facet status label | Facet shows `active` lowercase | Consider `list_item` labels or allowed_values labels |
| Categories facet empty | No categorized published index content | Expected until editors assign `field_category` |
| Reset search button | Only visible when keywords query present | Document for editors |

## Confusing workflows

None identified that block staging. Moderation descriptions added via theme `form_alter` improve clarity without new modules.

## Editorial testing checklist (post-migration)

- [ ] Create draft stallion → confirm absent from `/search` and `/stallions`
- [ ] Publish with category → confirm categories facet counts
- [ ] Add featured_content paragraph to homepage → verify listing
- [ ] Run content health reports as content editor role
- [ ] Upload media without alt → confirm media_governance surfaces item
