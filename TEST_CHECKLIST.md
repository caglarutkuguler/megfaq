# megfaq — manual test checklist

Verify on a real PrestaShop shop after applying the QA diff. Prefer **PS 1.7.8**, **PS 8.0**, and **PS 8.1+/9** for the FAQ-page matrix. Multilingual + multistore shops exercise the major fixes.

## Install / uninstall

- [ ] Install on a clean shop; tables `megfaq`, `megfaq_lang`, `megfaq_shop` exist.
- [ ] Default settings match Readme (product block on, FAQ page on, ask on).
- [ ] Uninstall shows data-loss warning; tables and `MEGFAQ_*` keys are gone afterward.

## Compatibility (critical — bug #1)

- [ ] On **PS 1.7.x / 8.0.x**: enable FAQ page; open `/faq` and the language-prefixed URL — page loads (no 500). Bare `/faq` 302s to the language URL when friendly URLs are on.
- [ ] On **PS 8.1+ / 9**: same — `/faq` loads; canonical redirect still works.
- [ ] BO configure page icons render on PS 9 (Material Symbols).

## Admin — entries

- [ ] Add a **shared** entry (no product); appears on multiple product pages.
- [ ] Add a **product-specific** entry; sorts above shared on that product.
- [ ] Edit, publish, unpublish, delete; confirmation on delete.
- [ ] Filter pending / published / global / search + paging.
- [ ] Catalogue > 800 products: product field becomes ID input.

## Admin — settings

- [ ] Toggle product block, shared-on-product, FAQ page, open-first, language fallback.
- [ ] Ask form off / customers-only; flood limit 0 vs 3.
- [ ] Consent CMS page shows checkbox on FO.
- [ ] Notify email + answer mail on/off; receive `megfaq_new` / `megfaq_answered` (EN and TR).

## Storefront — product

- [ ] Accordion is server-rendered `<details>`; content visible with JS disabled.
- [ ] Ask form: valid submit → thank-you notice; honeypot filled → silent refuse; over flood → wait message.
- [ ] Guest vs logged-in when ask-who = customers.
- [ ] Prefill name/email when logged in.
- [ ] Deep link `#megfaq-{id}` opens the target row (JS).

## Storefront — ask endpoint (bugs #2, #5, #6)

- [ ] **GET** `module/megfaq/askquestion?id_product=X` redirects to the product (or home) **without** `mf_sent` / failure notice.
- [ ] **POST** without `token` (or with a wrong token) → refused (`mf_sent=2`), no new pending row.
- [ ] **POST** with valid form + token → thank-you; pending row created.
- [ ] With consent CMS configured: **POST** without `mf_consent` → refused; with checkbox → accepted.

## Storefront — FAQ page

- [ ] Shared group first (expanded); products as collapsed rows with counts.
- [ ] Search box: server `?q=` filters; typing filters without reload; clear restores.
- [ ] `?q=` response has noindex robots.
- [ ] Deleted-product entries hidden on FAQ page; still listed in BO as “Deleted product”.
- [ ] Single-group shop: no unnecessary collapse chrome.

## Multilingual (bug #3)

- [ ] Default language EN; ask from FO in FR (or another non-default lang).
- [ ] Open BO in EN: pending row shows the question text (seeded into default lang), not blank.
- [ ] Publish with default-lang answer works without retyping the question.
- [ ] Non-asked languages stay empty until translated; fallback on/off still behaves as before.

## Multistore (bug #4)

- [ ] Entry created in shop A not visible on shop B FO.
- [ ] BO list / pending / published / global counts in shop A show only shop A rows.
- [ ] Crafted `mf_edit` / delete / toggle for a shop-B id while in shop A is refused / no-op.

## GDPR

- [ ] Export includes questions for customer email.
- [ ] Erasure blanks name/email/id_customer; published answer remains.

## Widget / assets (bugs #7, #8)

- [ ] `{widget name='megfaq'}` on a product **with** entries renders the list.
- [ ] `{widget name='megfaq'}` on a product with **no** entries but ask enabled shows the ask form (not empty).
- [ ] Product page and `/faq` include `front.css` / `front.js`.
- [ ] Cart / checkout / CMS (no widget) do **not** load megfaq front assets.
- [ ] Note: widget placed on a non-product CMS page will not get megfaq CSS/JS after the asset-scope fix — confirm theme placement or accept as residual.

## Configuration (not fixed — verify current behaviour)

- [ ] Multistore: change settings under “all shops” vs one shop; document which shops inherit (no explicit `updateGlobalValue` in module).

## Negative / security smoke

- [ ] Honeypot filled → refuse, no row.
- [ ] Flood limit: N+1th question in an hour → wait message.
- [ ] Cross-origin style POST without token fails (CSRF).

## Security fixes in 1.2.1 — verify each

Run the unit tests first; four of the seven checks below have an automated half
that fails faster than a shop does.

```
php tests/ValidatorTest.php     # input rules
php tests/QueryTest.php         # shop scoping, notification budget SQL
php tests/FaqPageTest.php       # FAQ page grouping and redirects
php tests/AdsWidgetTest.php     # the two ads-widget URL predicates
php tests/ReviewNudgeTest.php   # review nudge
```

All five must print `OK`. `php -l` on 7.4 and 8.2 must be clean
(`_tools/php-lint.sh megfaq` and `_tools/php-lint.sh megfaq 7.4-cli-alpine`).

### Storefront output escaping (MF-1, no longer a finding)

The escape lives in PHP (`MegFaq::escape()` / `MegFaqFaqModuleFrontController::group()`)
and the templates print the already-escaped value with `nofilter`. Escaping again
in the template double-encodes. This is a regression watch, not a fix to verify:

- [ ] Publish a FAQ whose question and answer contain `&`, `<`, `>` and quotes — the
      storefront shows them as text, not markup, and **not** as `&amp;`
- [ ] A multiline answer still shows its line breaks as breaks, not as `<br />` text
- [ ] Ask-a-question still stores `strip_tags`-cleaned text and appears after moderation

### Ads widget: host pin (MF-2, CWE-918)

- [ ] Configure page still renders the MEG promo widget when megventure.com is reachable
- [ ] Widget stays empty and silent when the feed is down — no error, no blank panel,
      and the rest of the configure page is unaffected

### Ads widget: feed addresses (MF-3, CWE-79)

No live shop needed for the first three; `tests/AdsWidgetTest.php` covers them.
To see it end to end, point `MegVentureAdsWidget::render()` at a local file
serving a hand-written feed **on a scratch install only** — never against
megventure.com or a live shop:

- [ ] A feed item with `"link_url": "javascript:alert(1)"` renders **no card** for that
      item (the card is skipped, the rest of the grid is unaffected)
- [ ] A feed item with `"image_url": "data:text/html;base64,..."` renders the card with
      **no `<img>`**, title and price intact
- [ ] `"shop_logo_url"` / `"shop_url"` that are not http/https produce a header with no
      logo and no "Visit" link, rather than a broken one
- [ ] An ordinary feed of http/https items renders byte for byte as it did before

### Ask endpoint: product must belong to this shop (MF-4, CWE-639)

Needs a multistore shop with a product in shop B that is **not** associated with
shop A:

- [ ] POST to shop A's `module/megfaq/askquestion` with a valid token and shop B's
      `id_product` → refused (`mf_sent=2`), **no row** in `megfaq`, **no mail**
- [ ] POST with one of shop A's own product ids → accepted as before
- [ ] A product that is associated with shop A but currently **disabled** or out of
      stock is still accepted — association is the rule, not visibility
- [ ] Single-shop install: nothing changes; every normal submission still works

### Merchant notification budget (MF-5, CWE-770)

`MEGFAQ_NOTIFY` on, `MEGFAQ_FLOOD` at its default:

- [ ] Send 21 questions inside one hour, each with a **different** e-mail address
      (this is the case `MEGFAQ_FLOOD` never caught): the first 20 produce a
      `megfaq_new` mail, the 21st and later do not
- [ ] All 21 rows exist in `megfaq`, all unpublished, and the Questions tab badge
      counts all 21 — nothing is lost, only the mail stops
- [ ] Exactly **one** log line is written, at the 21st question, naming the shop
- [ ] Wait out the hour (or move `date_add` back): the next question mails again
- [ ] A normal shop taking a handful of questions an hour sees no change at all
- [ ] Publishing an answer still sends `megfaq_answered` to the shopper — the budget
      applies to the merchant notification only
- [ ] Multistore: flooding shop A does not stop shop B's notifications

### Back-office write permission (MF-6, CWE-862)

Create an employee profile with **View** on Modules and **not** Edit, and a
second employee on it:

- [ ] That employee can open the megfaq configure page and read the list
- [ ] Add / save entry, Publish, Unpublish, Delete and Save settings each show
      "Your account may open this page but not change anything on it" and change
      **nothing** in the database
- [ ] The same profile with Edit granted can do all of it
- [ ] SuperAdmin is unaffected
- [ ] A profile with **no** `access` row for the Modules tab is **not** locked out —
      the check fails open by design; confirm saving still works for it
- [ ] The review-nudge "No thanks" link still works for a view-only employee

### Direct-access guards (MF-7)

- [ ] `GET /modules/megfaq/classes/MegVentureAdsWidget.php` and
      `GET /modules/megfaq/upgrade/upgrade-1.2.1.php` return nothing useful
      (403 on Apache via `.htaccess`; a blank 200 on nginx, not a PHP error and not
      a class/function being run)
- [ ] The module still installs, upgrades from 1.2.0, and renders the ads widget —
      i.e. the guard did not break the legitimate `require_once` paths


## Security fixes in 1.2.2 — CSRF POST gate (MF-9)

- [ ] Module at **1.2.2** (fresh install or upgrade from 1.2.1)
- [ ] Configure → Save settings / save entry / toggle / delete still work via POST forms
- [ ] GET `...&submitMegFaqSettings=1&MEGFAQ_ASK=0` does **not** change settings
- [ ] GET `...&submitMegFaqDelete=1&mf_id=N` does **not** delete entry N
- [ ] GET `...&submitMegFaqToggle=1&mf_id=N` does **not** publish/unpublish
- [ ] Prior 1.2.1 checks (MF-5 mail budget, MF-6 view-only, MF-4 shop product, ads URL) still pass
- [ ] `tests/SecurityGuardsTest.php` passes under PHP 8.2
