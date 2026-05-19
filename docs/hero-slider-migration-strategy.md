# Hero slider migration strategy

Documentation for migrating Drupal 7 `content_slider` nodes into Drupal 11 `hero_slide` paragraphs on the Homepage content type. **No automated migration is implemented in this phase.**

## Legacy audit: `content_slider` (3 nodes)

| D7 field | Type | Notes |
|----------|------|--------|
| `field_content` | Text (plain) | Slide copy / headline source |
| `field_slider_image` | Image | File ID + optional alt |

No legacy CTA, title, or link fields.

## D11 target architecture

| D11 field | Type | Maps from D7 |
|-----------|------|----------------|
| `field_title` | Text | Node title or first line of `field_content` (editorial) |
| `field_description` | Text (formatted) | `field_content` |
| `field_image` | Media (image) | `field_slider_image` via `wcf_d7_media_image` |
| `field_cta_text` | Text | No source — leave empty or set manually |
| `field_cta_url` | Link | No source — leave empty |

**Parent entity:** `homepage` node → `field_hero_slides` (unlimited `hero_slide` paragraphs, drag-and-drop order).

## Recommended manual recreation workflow

1. Confirm media migration: legacy slider files appear in **Media → Image**.
2. Create or edit the **Homepage** node (`/node/add/homepage` or existing homepage).
3. For each of the 3 legacy slides:
   - Add **Hero slide** paragraph.
   - **Title:** use D7 node title or derived headline from `field_content`.
   - **Description:** paste `field_content`.
   - **Image:** select migrated media matching `field_slider_image`.
   - **CTA:** only if editorial wants a button (no D7 equivalent).
4. Set **Configuration → Basic site settings → Front page** to this homepage node path.
5. Reorder slides in the Paragraphs UI to match legacy order (by D7 `created` or manual audit).

## Future automated migration (not implemented)

1. Ensure `field_slider_image` files are in `wcf_d7_file` / `wcf_d7_media_image` (`WcfD7ImageFile` already references `field_slider_image`).
2. Add `wcf_d7_paragraph_hero_slide`:
   - Source: `d7_node` with `node_type: content_slider`
   - Destination: `entity:paragraph` bundle `hero_slide`
   - Process `field_image` via `migration_lookup` on file ID
   - Process `field_description` from `field_content`
   - Process `field_title` from node title or truncated `field_content`
3. Add `wcf_d7_homepage_hero_slides` (or post-migration script) to attach paragraph IDs to a homepage node’s `field_hero_slides` in sort order.

### Open decisions

- Whether `field_content` becomes title, description, or split across both.
- Default CTA for slides that had no link in D7.
- Sort order: D7 `created`, manual weight, or migration `delta`.

## Out of scope (this phase)

- Migration YAML for `content_slider` nodes.
- Layout Builder sections beyond field architecture.
- Panels / Display Suite / Context porting.

## Related config

- Paragraph: `config/sync/paragraphs.paragraphs_type.hero_slide.yml`
- Homepage: `config/sync/node.type.homepage.yml`, `field.field.node.homepage.field_hero_slides.yml`
- Theme: `web/themes/custom/wcf_theme/`
- Module notes: `web/modules/custom/wcf_migrate/docs/HERO_SLIDER_MIGRATION_STRATEGY.md`
