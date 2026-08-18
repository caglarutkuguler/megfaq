# Changelog

## 1.0.0

First release.

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
