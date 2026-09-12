# Changelog

## 1.2.0

### Added

- **The FAQ page can be searched.** A search box at the top of the page
  filters the questions as you type. It looks in the question, the answer and
  the product name, hides everything that does not match, highlights the word
  in what is left, and says how many results there are. Up to ten results
  open on their own so the answer is on screen; more than that stay closed
  and you pick one - or type one more word.

  The search works with no script at all: the box is an ordinary form the
  server answers from `?q=`, which is also what a shared link to a search
  loads. A search address carries `noindex`, so it never competes with the
  page itself in a search engine.

### Changed

- **One row per product instead of one section per product.** The page used
  to open with a two-column list of every product that had questions, and
  then print every question under every product in full. On a shop with a
  few dozen products that was a long page with a long index on top of it,
  and product names of any length made the index itself hard to scan.

  Now the shared answers come first, expanded, and each product is a single
  collapsed row showing its name and how many questions it holds. Open the
  row to read them; the link to the product page sits at the end. A shop
  with only one group gets the plain list, with nothing to collapse. Deep
  links to a question (`#megfaq-12`) still work and open the row they point
  into.

- The page now declares its canonical address. PrestaShop gives module pages
  none, so until now the FAQ page had none either.

- The front stylesheet and script are registered with the module version as
  their cache key, so a browser holding the old copies fetches the new ones
  on its first visit after the upgrade. The upgrade script also drops the
  theme's combined-asset cache, which is keyed on file paths and would
  otherwise keep serving the old bundle.

## 1.1.1

### Fixed

- **The back-office icons turned into empty boxes on PrestaShop 9.** The 5
  icons the module draws for itself came from FontAwesome 4, which the back
  office shipped up to PrestaShop 8. PrestaShop 9 replaced it with Material
  Symbols Outlined, and FontAwesome now reaches the page only through
  `themes/default/public/theme.css` — a leftover of the old theme rather than
  anything the new back office asks for.

  An icon-font class does not name a picture; it selects a private-use code
  point that means nothing without that exact font file. So the moment the
  font is not there the browser has nothing to fall back to and draws a
  placeholder box. That makes the failure abrupt rather than gradual, and it
  shows up first on a page load with a freshly cleared asset cache.

  The icons now come from the set the core loads for its own interface, where
  the icon name is the element’s text rather than a class. They are sized
  down from its 24px default and set back to inheriting the surrounding text
  colour, so they sit exactly where the FontAwesome ones did. Nothing in the
  interface moves or changes name.

## 1.1.0

### Added

- A single review-request line on the module's own configuration page. It
  appears at the earliest 21 days after installing, asks once for a short
  review on megventure.com, and disappears forever after a click, a
  "No thanks", or three unanswered views. It makes no outbound request of any
  kind and stores nothing beyond three prefixed configuration values, which
  uninstalling removes.

## 1.0.0

First release.

### Installing on a database that has been restored

Two of the install steps write a new row to a PrestaShop table rather than to
one of ours: registering a hook the shop does not have yet, and writing the
default settings. On a database restored from a backup that lost an
AUTO_INCREMENT somewhere, those writes fail, and the module manager reports the
whole install as `PrestaShop could not install this module` with nothing to go
on.

The install now says which step failed, in the back office log and in the PHP
error log, and it no longer refuses to install over the two failures that do not
matter:

- The two GDPR hooks are optional. Without them the module cannot answer an
  export or erasure request, which is worth a warning and is not worth blocking
  the install for.
- A setting that will not save is not fatal either, because `getSettings()`
  falls back to the same defaults the installer writes. The merchant can save
  the settings screen later and it will take.

Failing to create the module's own three tables is still fatal, because nothing
works without them.

### What it does

- **An answer can belong to one product or to all of them.** This is the whole
  design. Most FAQ modules make a merchant write the same answer once per
  product; a shop with forty products writes it twice and gives up. Here an
  entry with no product id appears on every product page and in the shop section
  of the FAQ page, and a product's own entries appear above them.

- **Shoppers can ask.** A question from a product page arrives unpublished, with
  no answer, in the same list as everything else - because a question and a FAQ
  entry are the same thing at two stages of one life, not two kinds of record.
  Writing the answer and publishing it is what turns one into the other, and
  that is also the moment the shopper is told, rather than on receipt, which
  tells them nothing.

- **One FAQ page.** Shared answers first, then a section per product with a link
  back to it. A question answered on a product page is only findable by someone
  already on that page; this is the page you can hand to a search engine, an
  answer engine or a customer.

- **Translated at the shop's own pace.** Each entry holds a question and answer
  per language. Nothing is machine-translated on the merchant's behalf. An entry
  with no complete translation falls back to the shop's default language rather
  than disappearing - otherwise a shop that translates over several months has
  an empty FAQ page in seven languages until the last day. The fallback takes
  question and answer together, from the same language: a Polish question above
  an English answer reads as a mistake and leaves the shopper unsure the answer
  is even about their question. Merchants who prefer the entry hidden until it
  is translated can switch the fallback off.

- **A FAQ page per language, and a short address that leads to it.** The page
  exists once per language, at that language's own address, and the settings
  screen lists all of them rather than the one belonging to whichever language
  the employee happens to be working in.

  `/faq` without a language prefix also resolves, which is convenient and, left
  alone, quietly damaging: the same content would answer at two addresses, and a
  crawler arriving at the short one carries no language cookie, so it would only
  ever see the default language there. So the short address stays usable - type
  it, share it, print it - and hands the visitor to their own language's page
  with a 302. One indexable, hreflang-able address per language, nothing
  duplicated.

- **The product is chosen by name, not by id.** Attaching an entry to a product
  used to mean leaving the screen, finding the id, coming back and typing it,
  and a typo produced an entry attached to a product nobody meant. It is a list
  now, with the shared option named for what it does rather than shown as `0`.
  A catalogue too large for a list falls back to the id field and says why. An
  entry whose product has since been deleted keeps that product in the list, so
  saving does not quietly turn it into a shared answer.

- **Everything server-rendered.** The accordion is a `<details>` element, so the
  text is in the HTML from the first byte, selectable, findable with the
  browser's own search, and readable by anything that fetches the page - whether
  or not any JavaScript runs.

### What it deliberately does not do

- **No FAQPage structured data.** Google stopped showing FAQ rich results on
  7 May 2026 and removed the documentation on 15 June 2026. The markup is not
  penalised, it is simply ignored, and shipping it would let the module claim a
  search feature that no longer exists. The content is what still works.

- **No machine translation, no invented answers.** An entry the merchant has not
  written in a language is absent in that language rather than approximated.

### Privacy

A question carries a name and an address, so the module answers both GDPR hooks.
Export returns the questions asked from an address. Erasure blanks the asker -
name, address and customer id - and leaves the answer, which is the shop's own
writing and which other shoppers are relying on.
