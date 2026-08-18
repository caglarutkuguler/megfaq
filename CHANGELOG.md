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
  per language. A language with no answer simply does not show the entry, so
  nobody is ever served a question in a language they did not choose, and
  nothing is machine-translated on the merchant's behalf.

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
