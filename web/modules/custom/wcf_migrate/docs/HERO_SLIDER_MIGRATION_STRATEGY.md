# Hero slider migration strategy (Phase 7 — documentation only)

## Legacy source: `content_slider` (3 nodes)

Do **not** migrate `content_slider` as nodes. Target model: `hero_slide` paragraphs (existing in D11 config sync).

### Audited D7 structure

| Field | Type | Purpose |
|-------|------|---------|
| `field_content` | Text (plain) | Short slide text / headline source |
| `field_slider_image` | Image | Slide image (`field_slider_image_fid`, alt optional) |

No separate CTA fields, title field, or link field on the legacy bundle.

### D11 target: `hero_slide` paragraph

| Field | Type | Maps from D7 |
|-------|------|----------------|
| `field_title` | Text | Derive from `field_content` or node title (editorial decision) |
| `field_description` | Text (formatted) | `field_content` |
| `field_image` | Media (image) | `field_slider_image` via `wcf_d7_media_image` lookup |
| `field_cta_text` | Text | **No D7 source** — leave empty or set site default |
| `field_cta_url` | Link | **No D7 source** — leave empty |

### Recommended migration path (future implementation)

1. **Reuse file/media pipeline** — Ensure `field_slider_image` files are included in `wcf_d7_file` / `wcf_d7_media_image` (already referenced by `WcfD7ImageFile` source).
2. **New migration: `wcf_d7_paragraph_hero_slide`**  
   - Source: `d7_node` with `node_type: content_slider`  
   - Destination: `entity:paragraph` bundle `hero_slide`  
   - Process `field_image` as media `migration_lookup` on `fid`  
   - Process `field_description` from `field_content`  
   - Process `field_title` from node `title` or truncated `field_content`
3. **Parent attachment** — Separate migration or manual Layout Builder / paragraph reference field on a “Home” node or block (architecture TBD; not in current rebuild config).
4. **Ordering** — After `wcf_d7_media_image`, before or in parallel with homepage assembly work.

### Open decisions (require product/editorial input)

- Where hero slides attach in D11 (front page node, custom block, Layout Builder section).
- Whether `field_content` becomes title, description, or both.
- Default CTA text/URL for slides that had no link in D7.
- Sort order: use D7 node `weight` if added later, or `created` / manual `delta`.

### Out of scope for Phase 7

- No migration YAML for `content_slider` nodes.
- No paragraph parent entity migration until homepage IA is confirmed.
