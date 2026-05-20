# WCF newsletter migration (D7 `wcf_news` → D11 `wcf_newsletter`)

## D7 source

| Item | Detail |
|------|--------|
| Table | `wcf_news` (27 active rows, years 2010–2015) |
| Files | `sites/default/files/event_file/*.pdf` |
| Public route | `/news` (`news` custom module) |
| Intro copy | D7 page nid 24 “News Header” (`wcf_field_data_body`) |

## D11 destination

| Item | Detail |
|------|--------|
| Bundle | `wcf_newsletter` |
| Fields | `field_newsletter_year`, `field_newsletter_month`, `field_newsletter_file` (document media), `field_newsletter_legacy_id`, `field_newsletter_weight` |
| Listing | View `wcf_news` at `/news` (theme accordion by year; intro in view header) |

**Note:** D7 page nid 24 (“News Header”) collides with a stallion in D11. Intro copy lives in the view header config (editable under Structure → Views → News).

## Import

```bash
# Import config
ddev drush cim -y

# Run import (requires D7 DDEV + files under sites/default/files/event_file)
ddev drush php:script scripts/wcf-import-newsletters.php

# Dry run
ddev drush php:script scripts/wcf-import-newsletters.php -- --dry-run
```

Optional: export TSV first:

```bash
cd ../drupal7-legacy && ddev mysql -N -B -e \
  "SELECT id,year,month,title,upload_file,status,timestamp FROM wcf_news WHERE status=1 ORDER BY year DESC,id DESC" \
  > ../drupal11-upgrade/tmp/newsletter-data.tsv
```

## Deferred

- Newsletter email signup (`wcf_email_us` / `email_subscribe_form`) → future Webform
- D7 static promo blocks below the accordion (images/news copy) → optional page paragraphs
