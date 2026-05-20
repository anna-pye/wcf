# Registry logo editorial guidance

This document supports editors maintaining the homepage **Recognised bloodlines** section via the **Registry logo** content type (`wcf_registry_logo`).

## Permission and copyright

- **Do not use third-party logos unless the client has written permission** to display them on the public website.
- **Do not download, scrape, or copy logos** from organisation websites into the CMS unless they are officially supplied for this purpose.
- Automated tools (including Cursor agents) **must not** fetch or commit unofficial logo files.

## Uploading approved logos

1. Obtain logo files from the client or an official brand pack.
2. Add the file through **Media** (recommended library workflow).
3. Create or edit a **Registry logo** node and attach the media in **Logo**.
4. Set **Organisation name** (used for labels and image alt text).
5. Optionally set **Short name**, **Official website**, **Fallback colour**, and **Weight**.

If no logo is uploaded, the homepage shows a **colour swatch placeholder** using the selected fallback colour.

## File formats

| Format | Guidance |
|--------|----------|
| **SVG** | Preferred when supplied officially; scales cleanly on cream/white backgrounds. |
| **PNG** | Use transparent backgrounds where possible. |
| **JPEG** | Avoid for logos with transparency needs. |

## Alt text

- Alt text is taken from **Organisation name**.
- Keep the name accurate (e.g. “Australian Warmblood Horse Association”), not marketing slogans.

## Homepage display rules

- Up to **five** published registry logo nodes appear on the homepage.
- Sort order: **Weight** ascending, then **Title** ascending.
- Unpublished nodes never appear.
- Cards link only when **Official website** is set and the URL is accessible to the current user.
- If no published registry logo nodes exist, the theme shows **static fallback swatch cards** with default organisation labels (no images).

## Suggested official sources (for editors only)

Use these sites to confirm naming and to request official assets — **do not** scrape images from them:

- [Arabian Horse Society of Australia](https://www.ahsa.asn.au/)
- [Australian Pony Stud Book Society](https://apsb.asn.au/)
- [Australian Stock Horse Society](https://ashs.com.au/)
- [Australian Warmblood Horse Association](https://www.awha.com.au/)
- [Riding Pony Stud Book Society of Australia](https://www.rpsbs.com.au/)

Australian Studbook and other bodies may require separate client approval; confirm with WCF before publishing.

## Related configuration

- Content type: `config/sync/node.type.wcf_registry_logo.yml`
- Theme render: `wcf_theme_build_homepage_registry_items()` in `wcf_theme.theme`
- Template: `templates/components/wcf-registry-legend.html.twig`
