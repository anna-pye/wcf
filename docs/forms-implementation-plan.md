# WCF Contact & Nomination Forms — D11 Implementation Plan

Date: 2026-05-20  
Branch: `feature/wcf-contact-nomination-forms`

## Architecture decision

| Decision | Rationale |
|----------|-----------|
| **Webform** for both forms | Standard D11 pattern; config-exportable; email handlers; access control; no custom procedural port of D7 modules |
| **Honeypot** for spam | No `webform`, `captcha`, or `honeypot` in `composer.json` / `config/sync` today; no reCAPTCHA/hCaptcha keys in repo — Honeypot first per task |
| **No custom DB tables** | Legacy storage schemas diverged from runtime; Webform submission storage (default, admin-only) replaces `{contact_us}` / `{nomination}` unless editorial needs export |
| **Email to `system.site:mail`** | Legacy used `site_mail` only; D11 `config/sync/system.site.yml` → `mail` |
| **Reply-To: submitter email** | Matches D7 `drupal_mail(..., $from)` behaviour |
| **Theme: `wcf_theme` SCSS** | Minimal `.webform-submission-form` layout in `_forms.scss`; no Twig duplication of form markup |

### Modules to add

```bash
composer require drupal/webform:^6.3@beta drupal/honeypot:^2.2
drush en webform webform_ui honeypot -y
```

**Note:** Webform `6.3.0-beta9` is required for Drupal 11; stable 6.2.x targets Drupal 10 only.

**Not adding:** reCAPTCHA (no keys/config in project).

### Webforms

| ID | Title | Path | Storage |
|----|-------|------|---------|
| `wcf_contact` | Contact us | `/contact-us` | Default Webform (admin review); no public submission view |
| `wcf_nomination` | Nomination | `/nomination` | Same |

### Contact form fields (legacy-aligned)

| Element | Type | Required |
|---------|------|----------|
| `name` | textfield | yes |
| `email` | email | yes |
| `phone` | tel | yes (legacy required phone) |
| `message` | textarea | yes |
| `privacy_note` | processed_text / markup | no |

No subject field (not in D7).

### Nomination form fields (modernized structure, legacy coverage)

Grouped fieldsets:

1. **Mare details** — mare_name, birth_year, color_mare, sabino, frame_overo, tobiano, dilute, sire, dam, mare_status, last_service (date or text), through_bred, breed, mare_registered, society  
2. **Service & stallion** — service_required, conception_month, preferred_stallion (text; replaces hardcoded `stalian_choice`)  
3. **Your details** — first_name, last_name, phone, mobile, business_name, email, address_line1, address_line2, town, postcode, state, how_heard  
4. **Additional** — message, terms (checkbox)  
5. **privacy_note** — markup

**Required (D11):** Task minimum + legacy-critical breeding fields:

- name fields: first_name, last_name (or combined — using first/last to match legacy)
- email, phone, mare_name, preferred_stallion, message
- Plus legacy-validated core: birth_year, color_mare, sire, dam, mare_status, service_required, conception_month, terms

**Optional vs D7:** fax_no, business_name, mobile, full address fields — present but not all required (D7 over-required; reduces abandonment). Documented in validation doc.

### Email handlers

| Form | To | Subject prefix | Reply-To |
|------|-----|----------------|----------|
| wcf_contact | `[site:mail]` | `WCF Contact` | `[webform_submission:values:email]` |
| wcf_nomination | `[site:mail]` | `WCF Nomination` | `[webform_submission:values:email]` |

### Confirmation

- Inline message (no public token URL)
- Contact: thank-you text aligned with legacy intent
- Nomination: success text aligned with legacy intent
- No redirect exposing submission data

### Honeypot

- Enable module globally
- Protect both webforms via `honeypot.settings` / webform third-party or global form IDs

### Menu links

- Legacy links were theme-hardcoded, not exported menu config in D7 repo
- Add **menu link config** to `footer` and/or `main` if pattern exists post-export; otherwise document for editorial follow-up

### Theme

- `web/themes/custom/wcf_theme/src/scss/components/_forms.scss`
- Import from `style.scss`
- `npm run build` → `css/style.css`

### Out of scope

- Porting D7 admin list UIs (`admin/contact_us`, `admin/nomination`)
- Google Maps block from contact template (page content, not form)
- Newsletter `email_subscribe_form` block
