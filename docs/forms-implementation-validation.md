# WCF Contact & Nomination Forms — Implementation Validation

Date: 2026-05-20  
Branch: `feature/wcf-contact-nomination-forms`

## Legacy evidence summary

See [forms-legacy-audit.md](forms-legacy-audit.md).

- **Contact:** D7 `contact_us` module at `/contact-us`; fields fullname, email, phone, message; email to `site_mail`; D7 Captcha.
- **Nomination:** D7 `nomination` module at `/nomination`; extensive mare/service/contact fields; email to `site_mail`; hardcoded stallion radios; D7 Captcha.

## D11 implementation

| Item | Implementation |
|------|----------------|
| Modules | `drupal/webform` **6.3.0-beta9**, `drupal/honeypot` **2.2.2**, `webform_ui` |
| Contact webform | `wcf_contact` → `/contact-us` |
| Nomination webform | `wcf_nomination` → `/nomination` |
| Spam | Honeypot on both form IDs (no reCAPTCHA) |
| Theme | `wcf_theme` `_forms.scss` → compiled `css/style.css` |
| Storage | Webform default submission storage (admin-only); no custom SQL tables |

Architecture: [forms-implementation-plan.md](forms-implementation-plan.md).

## Fields created

### `wcf_contact`

| Element | Type | Required |
|---------|------|----------|
| privacy_note | processed_text | no |
| name | textfield | yes |
| email | email | yes |
| phone | tel | yes |
| message | textarea (max 5000) | yes |

### `wcf_nomination`

| Section | Elements |
|---------|----------|
| Mare details | mare_name, birth_year, color_mare, sabino, frame_overo, tobiano, dilute, sire, dam, mare_status, last_service_date, through_bred, breed, mare_registered, society |
| Service & stallion | service_required, conception_month, **preferred_stallion** (text; replaces D7 hardcoded list) |
| Your details | first_name, last_name, phone, mobile, business_name, email, address lines, town, postcode, state, how_heard |
| Additional | message, terms (checkbox) |
| Privacy | privacy_note |

**Required in D11:** mare_name, birth_year, color_mare, sire, dam, mare_status, service_required, conception_month, preferred_stallion, first_name, last_name, phone, email, message, terms.

**Deviation from D7:** D7 required many optional contact fields (fax, business_name, full address, colour modifiers, etc.). D11 keeps fields available but does not require all D7 validators — reduces abandonment while preserving data capture. Colour modifiers optional in D11.

## Email handler recipient source

| Setting | Value |
|---------|-------|
| To | `[site:mail]` from `config/sync/system.site.yml` (`anna_pye@icloud.com` in dev export) |
| From | `[site:mail]` / `[site:name]` |
| Reply-To | `[webform_submission:values:email]` |
| Contact subject | `WCF Contact: [webform_submission:values:name]` |
| Nomination subject | `WCF Nomination: [webform_submission:values:mare_name]` |

**Uncertainty:** Production WCF may have used `info@winningcoloursfarm.com.au` as `site_mail`; confirm with stakeholder before go-live.

## Spam protection

- **Honeypot** enabled for:
  - `webform_submission_wcf_contact_add_form`
  - `webform_submission_wcf_nomination_add_form`
- Time limit: 5 seconds (`honeypot.settings`)
- No reCAPTCHA (no keys in repository)

## Privacy notes

Both forms include a `privacy_note` element stating submitted information is used only to respond to the enquiry/nomination.

Confirmation uses inline messages with `confirmation_exclude_token: true` — no public submission URLs.

## Validation results (2026-05-20)

```text
ddev drush pm:list --status=enabled | grep -E "webform|honeypot"
  Honeypot (honeypot)       Enabled   2.2.2
  Webform UI (webform_ui)   Enabled   6.3.0-beta9
  Webform (webform)         Enabled   6.3.0-beta9

ddev drush config:get webform.webform.wcf_contact settings.page_submit_path
  /contact-us

ddev drush config:get webform.webform.wcf_nomination settings.page_submit_path
  /nomination

curl -I https://wcf11.ddev.site/contact-us   → HTTP/2 200
curl -I https://wcf11.ddev.site/nomination   → HTTP/2 200

ddev drush cst → No differences between DB and sync directory.
```

Theme build: `npm run build` in `web/themes/custom/wcf_theme` — success.

## Remaining uncertainties / follow-up

1. **Production site mail** — set `system.site:mail` to confirmed operations inbox before launch.
2. **Menu links** — D7 used theme template links (`url('contact-us')`, `url('nomination')`); no `menu_link_content` in config/sync. Add footer/main menu links in admin or follow-up config.
3. **Webform 6.3 beta** — using `6.3.0-beta9` for Drupal 11 compatibility; monitor stable 6.3 release.
4. **Default Webform `contact` install** — module ships example form `contact`; unused, can be archived in admin.
5. **Contact page body** — D7 template included map, phone numbers, staff cards; not part of webform (recreate as nodes/blocks if needed).
6. **Nomination stallions** — D11 uses free-text `preferred_stallion`; consider entity reference to stallion nodes later.
7. **Submission retention** — Webform purge not enabled; review GDPR/retention policy for breeding PII.
