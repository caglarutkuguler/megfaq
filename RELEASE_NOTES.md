# Product FAQ and Customer Questions - Shared Answers, Ask a Question and One FAQ Page — Release notes

_Only customer-relevant changes are listed. Internal refactoring, tests and housekeeping are omitted._

## 1.3.0

### New
- An entry can belong to several shops. With more than one shop, the entry form has a Shops checkbox list (the shops you may manage), so a shared answer is typed once instead of once per shop. A new entry starts with the shops of the current context ticked; an edit leaves the shops alone unless you change the ticks. The entry list has a new Shops column
- In All shops or a shop group the list shows, and you can edit, the entries of every shop in that selection. A warning on the configuration page explains that settings saved there become the default for every shop without its own value
- Deleting an entry no longer removes it from shops outside your selection. In a single-shop context an entry that also belongs to other shops is only taken out of this shop; it is deleted for good when no other shop holds it, and in All shops. The confirmation tells you which will happen
- With one shop none of this appears

### Fixed
- Entries with no shop association were invisible. Every storefront query joins the shop association, so an entry saved without a row there never appeared. The upgrade gives each such entry a row for the default shop. If some of those entries are published, they will now show on the storefront
- On a multistore, the form could never put one entry in more than one shop: the shop list was read but nothing set it

## 1.2.3

### New
- The configuration page links to the free MEG Venture Updates module, which installs updates for this module in one click. The line is hidden once that module is installed

## 1.2.2

### Fixed
- Configure writes (settings, entry save/delete/toggle) require POST. A GET carrying `submitMegFaq*` no longer mutates FAQ data or settings (CWE-352)
- The ask form cannot be used to flood the shop's own mail any more. The limit on questions per hour counts one e-mail address, and the address is a field in the form, so a script that changed it on every send was never limited by it - and every send made the shop e-mail the merchant. The notification is now capped at twenty an hour for the whole shop. Every question is still stored, still unpublished, and still counted by the badge on the Questions tab; only the mail about it stops, and only for the rest of that hour (CWE-770)
- An employee who may only view the back office can no longer change this module. PrestaShop opens a module's configure page on View permission alone and then asks the module nothing, so a read-only profile could delete entries, publish an unanswered question, or rewrite the settings. Writes from a profile whose Modules permission says it may not edit are now refused (CWE-862)
- A question can only be about a product this shop sells. On a multistore installation the ask form's product id was checked for existence but not for which shop it belonged to, so a question about another shop's product could be posted into this shop's queue - and the merchant's notification then named a product they have never sold (CWE-639)
- The two files that were missing the "refuse a direct request" guard every other PHP file in the module carries have it back
