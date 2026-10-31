# Product FAQ and Customer Questions - Shared Answers, Ask a Question and One FAQ Page — Release notes

_Only customer-relevant changes are listed. Internal refactoring, tests and housekeeping are omitted._

## 1.2.2

### Fixed
- Configure writes (settings, entry save/delete/toggle) require POST. A GET carrying `submitMegFaq*` no longer mutates FAQ data or settings (CWE-352)
- The ask form cannot be used to flood the shop's own mail any more. The limit on questions per hour counts one e-mail address, and the address is a field in the form, so a script that changed it on every send was never limited by it - and every send made the shop e-mail the merchant. The notification is now capped at twenty an hour for the whole shop. Every question is still stored, still unpublished, and still counted by the badge on the Questions tab; only the mail about it stops, and only for the rest of that hour (CWE-770)
- An employee who may only view the back office can no longer change this module. PrestaShop opens a module's configure page on View permission alone and then asks the module nothing, so a read-only profile could delete entries, publish an unanswered question, or rewrite the settings. Writes from a profile whose Modules permission says it may not edit are now refused (CWE-862)
- A question can only be about a product this shop sells. On a multistore installation the ask form's product id was checked for existence but not for which shop it belonged to, so a question about another shop's product could be posted into this shop's queue - and the merchant's notification then named a product they have never sold (CWE-639)
- The two files that were missing the "refuse a direct request" guard every other PHP file in the module carries have it back

## 1.2.0

### New
- The FAQ page can be searched. A search box at the top of the page filters the questions as you type. It looks in the question, the answer and the product name, hides everything that does not match, highlights the word in what is left, and says how many results there are. Up to ten results open on their own so the answer is on screen; more than that stay closed and you pick one - or type one more word
- The search works with no script at all: the box is an ordinary form the server answers from `?q=`, which is also what a shared link to a search loads. A search address carries `noindex`, so it never competes with the page itself in a search engine

### Improved
- One row per product instead of one section per product. The page used to open with a two-column list of every product that had questions, and then print every question under every product in full. On a shop with a few dozen products that was a long page with a long index on top of it, and product names of any length made the index itself hard to scan
- Now the shared answers come first, expanded, and each product is a single collapsed row showing its name and how many questions it holds. Open the row to read them; the link to the product page sits at the end. A shop with only one group gets the plain list, with nothing to collapse. Deep links to a question (`#megfaq-12`) still work and open the row they point into
- The page now declares its canonical address. PrestaShop gives module pages none, so until now the FAQ page had none either

## 1.1.1

### Fixed
- The back-office icons turned into empty boxes on PrestaShop 9. The 5 icons the module draws for itself came from FontAwesome 4, which the back office shipped up to PrestaShop 8. PrestaShop 9 replaced it with Material Symbols Outlined, and FontAwesome now reaches the page only through `themes/default/public/theme.css` — a leftover of the old theme rather than anything the new back office asks for
