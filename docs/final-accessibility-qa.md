# Final Accessibility & Search QA

**Date:** 2026-05-19  
**Scope:** `/` (homepage), `/stallions`, `/search`  
**Method:** Template + theme code audit (static); browser verification required on staging.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Landmarks | Pass (code) | `banner`, `main`, `contentinfo`, `aside` for filters |
| Heading hierarchy | Pass with caveat | Homepage uses visually hidden `h1`; section titles from paragraphs |
| aria-live | Pass (search) | Result count header `aria-live="polite"` |
| Focus states | Pass (cards) | `:focus-visible` on card links |
| Keyboard | Pass (code) | Native links and form controls; facet links focusable |
| Empty states | Pass | Search empty message `role="status"` |
| Mobile filters | Review | Sidebar stacks; form column on narrow viewports |

## Homepage (`/`)

| Check | Finding |
|-------|---------|
| Single h1 | `node--homepage--full.html.twig` — `h1.visually-hidden` with node label |
| Hero | Paragraph hero slides; CTA links built with `#type` link render arrays |
| Featured listings | Views-driven; card grid uses shared `stallion-listing__grid` |
| Landmarks | `page.html.twig` — `header[role=banner]`, `main#main-content`, `footer[role=contentinfo]` |

**Manual test:** Tab through hero CTA; verify slider controls if present; screen reader announces hidden h1.

## Stallions (`/stallions`)

| Check | Finding |
|-------|---------|
| Page title | `stallion-listing.html.twig` — `h1.stallion-listing-page__title` |
| Filters | `role="search"` + `aria-label="Filter stallions"` on exposed form region |
| Grid | `ul.stallion-listing__grid` — list semantics for card items |
| Cards | `article.stallion-card`; link `aria-labelledby` points to `h3` title id |
| Empty | View empty handler (verify in browser if no results) |

**Manual test:** Facet keyboard activation; focus return after filter apply; pagination `nav` labels.

## Search (`/search`)

| Check | Finding |
|-------|---------|
| Layout | `views-view--search-stallions.html.twig` |
| Live region | Header `aria-live="polite"` for result counts |
| Keyword form | `hook_form_alter` — `role="search"`, `aria-label` on exposed form |
| Results section | `section` + `aria-label="Search results"` |
| Empty state | `role="status"` on empty wrapper; message includes reset link |
| Pager | `nav` + `aria-label="Search results pages"` |
| Container home cards | Card view mode — same component as stallions (parity pass) |

**Manual test:**

1. Submit search — result count updates in live region.
2. Reset search — focus not lost in trap.
3. Mobile: facet sidebar readable; keyword form stacks (`_site-search.scss` max-width md).
4. Verify stallion + container_home cards in mixed results.

## Focus & contrast

| Component | Implementation |
|-----------|----------------|
| Card links | `outline: 2px solid` primary on `:focus-visible` |
| Facet links | underline on hover/focus |
| Status badges | Sold/retired/active color tokens in `_stallion-card.scss` |

Run automated contrast check (axe, Lighthouse) on staging with production content.

## Known gaps / deferred

| Item | Severity | Action |
|------|----------|--------|
| Facet block landmark | Low | Consider `aria-labelledby` tying facet `h2` to region |
| Low-res images | Content | Editorial replacement — not a11y template fix |
| Browser matrix | Ops | Chrome, Safari, Firefox + iOS Safari manual pass |

## Sign-off checklist (staging)

- [ ] Keyboard-only navigation: home → stallions → search → result → back
- [ ] Screen reader: search result announcement
- [ ] 200% zoom: no horizontal scroll on listing grids
- [ ] Reduced motion: hero slider respects `prefers-reduced-motion` (verify theme JS)

## Related

- `docs/search-validation.md`
- `docs/discovery-qa.md`
- `web/themes/custom/wcf_theme/templates/views/views-view--search-stallions.html.twig`
