# WCF Contact & Nomination Forms — Drupal 7 Legacy Audit

Audit date: 2026-05-20  
Legacy root: `/Users/anna/drupal7-legacy`  
Evidence scope: `sites/all/modules`, `sites/all/themes`, `sites/default` (no DB export reviewed).

---

## 1. Contact form (`contact_us` module)

### URL / routing

| Item | Evidence |
|------|----------|
| Public path | `contact-us` |
| Menu item | `contact_us_menu()` → `$items['contact-us']` |
| Access | `access callback => TRUE` with `access arguments => array('view contact us')` (effectively open) |

**Files:** `sites/all/modules/contact_us/contact_us.module` (lines 126–134), `contact_us.user.inc`

### Fields (form API)

| Machine name | Type | Required (form API) | Notes |
|--------------|------|---------------------|-------|
| `fullname` | textfield | `#required => TRUE` | Label “First Name”; placeholder default “Full Name” |
| `email` | textfield | `#required => TRUE` | Placeholder “Email” |
| `phone` | textfield | `#required => TRUE` | Placeholder “Phone” |
| `message` | textarea | `#required => TRUE` | Placeholder “Message” |
| `captcha` | captcha | (module element) | D7 Captcha module `#type => captcha` |

**No `subject` field** in legacy form.

**Files:** `contact_us.user.inc` (`contact_us_form()`, lines 27–53), template `templates/form/contact_us_form.tpl.php` (renders fullname, email, phone, message, captcha, submit)

### Validation (`contact_us_form_validate`)

- Rejects placeholder values: `Full Name`, `Email`, `Phone`, `Message`
- Email: `valid_email_address()`
- **Files:** `contact_us.user.inc` lines 57–83

### Email

| Item | Value |
|------|-------|
| Recipient | `variable_get('site_mail', '')` — Drupal `site_mail` |
| From / Reply-To | Submitter `email` passed as `$from` to `drupal_mail()` |
| Subject | `Contact US Information From WCF` |
| Body | HTML: Full Name, Email, Phone, Content (message) |
| **Files** | `contact_us_submit()` lines 107–114 |

### Spam / CAPTCHA

- D7 **Captcha** module (`sites/all/modules/captcha/`), image captcha available
- Form element: `#type => captcha` on both contact and nomination forms
- **Uncertainty:** Which captcha challenge type was enabled site-wide (image vs math) — not in module code; likely admin config in DB.

### Redirect / thank-you

- Success message: `Thank you for contacting with us.We will get in touch with you immediately.`
- Redirect: `contact-us` (same page, no separate thank-you URL)
- **File:** `contact_us_submit()` lines 114, 125

### Storage

- **Yes** — inserts row into `{contact_us}` via `contact_us_save(..., 'add')`
- Schema in `contact_us.install` lists `firstname`, `lastname`, `email`, `place_of_residence`, `message`, `type`, `created_on` — **schema does not match** fields saved in submit (`fullname`, `phone`). Likely schema drift / partial migrations. Submit saves: `fullname`, `phone`, `email`, `message`, `created_on`.
- Admin list: `admin/contact_us`
- **Files:** `contact_us.install`, `contact_us_submit()` lines 91–104

### Menu / theme links

- Theme footer/nav: `url('contact-us')` — “Contact Us”
- **Files:** `sites/all/themes/wcf/page.tpl.php`, `page--front.tpl.php` (multiple occurrences)

### Other (out of scope for public contact page)

- `email_subscribe_form` block in same module (homepage newsletter) — separate from contact form.

---

## 2. Nomination form (`nomination` module)

### URL / routing

| Item | Evidence |
|------|----------|
| Public path | `nomination` |
| Menu item | `nomination_menu()` → `$items['nomination']` |
| Access | `access callback => TRUE` with `view nomination` permission argument |

**Files:** `sites/all/modules/nomination/nomination.module` (lines 77–85), `nomination.user.inc`

### Fields (form API + template labels)

**Mare details**

| Machine name | Type | Template label (required marker) |
|--------------|------|----------------------------------|
| `mare_name` | textfield | Name of Mare * |
| `birth_year` | select | Year of Birth * |
| `color_mare` | textfield | Colour of Mare * |
| `sabino` | select | Sabino * |
| `frame_overo` | select | Frame Overo * |
| `tobiano` | select | Tobiano * |
| `dilute` | select | Dilute * |
| `sire` | textfield | Sire * |
| `dam` | textfield | Dam * |
| `mare_status` | select | Mares status * (Maiden / In Foal / Empty) |
| `dd`, `mm`, `yyyy` | select | Last service date (day / month / year) |
| `through_bred` | radios | Registered ASB Thoroughbred? |
| `breed` | textfield | Breed text when not thoroughbred |
| `mare` | radios | Registered with other Society? |
| `society` | textfield | Society name when yes |

**Service / stallion**

| Machine name | Type | Notes |
|--------------|------|-------|
| `service_required` | select | Live Cover / Walk-on Walk-off / A.I * |
| `conception` | select | Month (Jan–Sep in code) * |
| `stalian_choice` | radios | **Hardcoded stallions:** Thomas The Tank (AUS), Mega Charge, Profile in Style, GLACIAL GOLD (USA) — typo `stalian` in code |

**Contact / address**

| Machine name | Type |
|--------------|------|
| `firstname`, `surname` | textfield |
| `phone`, `mobile` | textfield |
| `business_name`, `fax_no` | textfield |
| `add1`, `add2` | textfield |
| `email` | textfield |
| `message` | textarea |
| `town`, `postcode` | textfield |
| `state` | select (AU states) |
| `how` | textfield | How did you hear about WCF? |
| `term` | checkbox | Terms acceptance |
| `captcha` | captcha | |

**Files:** `nomination.user.inc` (`nomination_form()`), `templates/form/nomination_form.tpl.php`

### Required fields (validation)

All validated as non-empty in `nomination_form_validate()`:

`mare_name`, `email`, `birth_year`, `color_mare`, `sire`, `dam`, `mare_status`, `service_required`, `conception`, `firstname`, `phone`, `surname`, `mobile`, `business_name`, `fax_no`, `add1`, `add2`, `message`, `town`, `postcode`, `state`, `how`, `sabino`, `frame_overo`, `tobiano`, `dilute`, `term` (must equal 1)

**Not validated in code:** `stalian_choice` (stallion choice) — **uncertainty:** may have been optional in practice.

**File:** `nomination.user.inc` lines 588–918

### Email

| Item | Value |
|------|-------|
| Recipient | `variable_get('site_mail', '')` |
| From / Reply-To | Submitter `email` |
| Subject | `Nomination From WCF` |
| Body | HTML listing all major fields (mare, genetics, service, contact, how) |
| Stallion label bug | Submit maps `stalian_choice` keys 1–3 only; option 4 (GLACIAL GOLD) may not appear correctly in email |
| **Files** | `nomination_submit()` lines 1057–1162 |

### Spam / CAPTCHA

- Same D7 Captcha `#type => captcha` as contact form.

### Redirect / thank-you

- Message: `You have been submitted successfully,We will get back to you as soon as possible.`
- Redirect: `nomination` (same page)
- **File:** `nomination_submit()` lines 1056, 1190

### Storage

- **Yes** — `db_insert('nomination')` with full field array from submit
- Admin: `admin/nomination`
- Install schema (`nomination.install`) defines simplified columns (`contact_name`, `contact_email`, …) — **does not match** wide insert in submit — likely schema outdated vs runtime table.
- **Files:** `nomination.install`, `nomination_submit()` lines 928–1055

### Menu / theme links

- `url('nomination')` — “Nomination Form” in WCF theme menus
- Product template links to `/nomination` as “Application form”
- **Files:** `page.tpl.php`, `page--front.tpl.php`, `product/templates/front/product_view.tpl.php`

---

## 3. Site mail / recipients (D7)

- Both forms use **`variable_get('site_mail')`** only — no separate nomination inbox in code.
- Public contact template shows **`info@winningcoloursfarm.com.au`** and **`peta@winningcoloursfarm.com.au`** as display-only mailto links (not form recipients).
- **Uncertainty:** Production `site_mail` may have been `info@winningcoloursfarm.com.au`; not confirmed without D7 DB/settings.php.

---

## 4. D7 modules summary

| Module | Path | Role |
|--------|------|------|
| `contact_us` | `sites/all/modules/contact_us/` | Custom contact form + DB + mail |
| `nomination` | `sites/all/modules/nomination/` | Custom nomination form + DB + mail |
| `captcha` | `sites/all/modules/captcha/` | Spam challenge (contrib) |

Core Drupal `contact` module exists in core but **not** used for these public forms (custom modules own the routes).

---

## 5. Uncertainties / risks for D11

1. **DB schema vs runtime** — install hooks do not match submit field lists; D11 should not replicate custom tables without business need.
2. **Production `site_mail`** — assume `system.site:mail` on D11 unless stakeholder confirms production inbox.
3. **Nomination stallion list** — hardcoded in D7; D11 should use free-text or entity reference to stallion nodes, not copy static list.
4. **Captcha type** — unknown; D11 will use Honeypot (no reCAPTCHA keys found in repo).
5. **Nomination required field set** — very strict on D7; D11 may modernize required set while keeping fields available (document in implementation plan).
6. **Admin submission review** — D7 stored submissions in custom tables; D11 Webform default storage is acceptable if documented and access-controlled.
