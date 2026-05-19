# Editorial category governance

**Status:** Active  
**Vocabulary:** `categories` (`taxonomy.vocabulary.categories`)  
**Field:** `field_category` on `stallion`, `container_home`, and `article`

---

## Approved categories

Only these governed terms may be used for horse and related editorial classification:

| Term | Use |
|------|-----|
| Foals | Young stock listings |
| Broodmares | Broodmare inventory |
| Stallions | General stallion classification (editorial, not bundle name) |
| ASB Stallions | Australian Stock Horse stallions |
| Show Mares | Show mare inventory |

Seed idempotently:

```bash
ddev drush php:script scripts/wcf-governed-categories.php
```

---

## Prohibited categories

Do **not** create or assign taxonomy terms for concepts handled elsewhere:

| Prohibited label | Use instead |
|------------------|-------------|
| For Sale | `field_status` = `active` on stallions |
| Sold | `field_status` = `sold` |
| Showcase | Homepage paragraphs / `field_featured` |
| Agistment | Not a horse classification on this site |
| Container Homes | `container_home` bundle, not categories |

---

## Lifecycle vs classification

| Dimension | Mechanism | Discovery |
|-----------|-----------|-----------|
| **Lifecycle** | `field_status` | `/stallions` filter, `/search` status facet |
| **Classification** | Governed `field_category` | `/search` category facet |
| **Marketing** | `field_featured`, homepage paragraphs | Sort on listings |

Categories answer “what kind of horse is this?” — not “is it for sale?” or “is it featured?”

---

## Migration from tags

One-time after config adds `field_category`:

```bash
ddev drush php:script scripts/wcf-migrate-tags-to-categories.php
```

Then remove legacy `field_tags` / `tags` vocabulary config and re-import.

---

## Technical controls

| Control | Location |
|---------|----------|
| No auto-create | `field.field.node.*.field_category.yml` |
| Autocomplete existing only | `entity_reference_autocomplete` widget |
| No editor term creation | `user.role.content_editor` — no `create terms in categories` |
| Operational visibility | `views.view.content_health` → Missing categories tab |
