<?php
/**
 * Product FAQ and Customer Questions
 *
 * Takes a question from the product page.
 *
 * It never renders anything. The form lives inside the product page, and the
 * answer to a submission is a redirect back to that page with a one-character
 * result code - so a refresh re-reads the product, not the submission, and the
 * shopper never sees a bare module URL in the address bar.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegFaqAskQuestionModuleFrontController extends ModuleFrontController
{
    /** Result codes, matching MegFaq::frontNotice(). */
    const SENT = 1;
    const REFUSED = 2;
    const TOO_MANY = 3;

    public function postProcess()
    {
        // Only a real POST is a submission. A GET (bookmark, bot, mistyped link)
        // must not run validation and must not flash "could not be sent".
        if (!$this->isPostRequest()) {
            return;
        }

        $idProduct = (int) Tools::getValue('id_product');

        $this->finish($idProduct, $this->handle($idProduct));
    }

    public function initContent()
    {
        // A GET on this route is not a submission. Send them somewhere real,
        // quietly — no mf_sent flash.
        $this->finish((int) Tools::getValue('id_product'), 0);
    }

    /**
     * @return bool
     */
    private function isPostRequest()
    {
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper((string) $_SERVER['REQUEST_METHOD'])
            : 'GET';

        return $method === 'POST';
    }

    /**
     * @return bool
     */
    private function isAskTokenValid()
    {
        $expected = $this->module->askFormToken();
        $given = (string) Tools::getValue('token');

        if ($expected === '' || $given === '') {
            return false;
        }

        return hash_equals($expected, $given);
    }

    /**
     * Whether this shop sells the product the question claims to be about.
     *
     * Validate::isLoadedObject() answers "does this row exist", which on a
     * multistore installation is a question about the whole installation. The
     * question, its notification mail and the back office row all belong to one
     * shop, so the product has to belong to that shop too.
     *
     * Association, not visibility: a question about a product that is currently
     * disabled or out of stock is a question the merchant wants, and a shopper
     * with the product page already open when it is switched off should not have
     * their question silently refused.
     *
     * @param int $idProduct
     *
     * @return bool
     */
    private function productIsInThisShop($idProduct)
    {
        $product = new Product((int) $idProduct);

        if (!Validate::isLoadedObject($product)) {
            return false;
        }

        return (bool) $product->isAssociatedToShop((int) $this->context->shop->id);
    }

    /**
     * @param int $idProduct
     *
     * @return int
     */
    private function handle($idProduct)
    {
        $settings = $this->module->getSettings();

        if (!(int) $settings['MEGFAQ_ASK'] || !$idProduct) {
            return self::REFUSED;
        }

        if (!$this->isAskTokenValid()) {
            return self::REFUSED;
        }

        if ($settings['MEGFAQ_ASK_WHO'] === 'customers' && !$this->context->customer->isLogged()) {
            return self::REFUSED;
        }

        // The honeypot. A field a person cannot see and will not fill in; a bot
        // filling in every input it finds will. Refused silently, with the same
        // message as any other refusal, because telling a bot why it failed is
        // how it learns.
        if (trim((string) Tools::getValue('mf_website')) !== '') {
            return self::REFUSED;
        }

        // Consent is required in HTML when a CMS page is configured; enforce the
        // same rule server-side so a non-browser client cannot skip it.
        if ((int) $settings['MEGFAQ_CONSENT_CMS'] && !(int) Tools::getValue('mf_consent')) {
            return self::REFUSED;
        }

        // The product has to be one this shop sells. Loading the object only
        // proves the row exists somewhere in the catalogue, and on a multistore
        // installation that is every shop's catalogue: id_product travels in the
        // form, so without this check a stranger can post a question about shop
        // B's product into shop A's queue, and shop A's merchant then gets a
        // notification naming a product they have never sold.
        if (!$this->productIsInThisShop($idProduct)) {
            return self::REFUSED;
        }

        $name = MegFaqValidator::cleanLine(Tools::getValue('mf_name'));
        $email = MegFaqValidator::cleanLine(Tools::getValue('mf_email'));
        $question = MegFaqValidator::cleanLine(Tools::getValue('mf_question'));

        if (!MegFaqValidator::isNameLength($name)
            || !MegFaqValidator::isEmail($email)
            || !MegFaqValidator::isQuestionLength($question)
            || MegFaqValidator::looksLikeSpam($question)
        ) {
            return self::REFUSED;
        }

        $flood = (int) $settings['MEGFAQ_FLOOD'];
        if ($flood > 0 && MegFaqEntry::countRecentByEmail($email, 60) >= $flood) {
            return self::TOO_MANY;
        }

        $entry = new MegFaqEntry();
        $entry->id_product = $idProduct;
        $entry->id_customer = (int) $this->context->customer->id;
        $entry->customer_name = $name;
        $entry->customer_email = $email;
        $entry->position = MegFaqEntry::nextPosition($idProduct);
        $entry->active = false;

        // The question is stored against the language it was asked in. When that
        // is not the shop default language, also seed the default-language row
        // with the same text so the back office list and publish rules (which
        // require a default-language question) can see it. Other languages stay
        // empty so publishing does not show a Polish question to a Turkish
        // shopper until someone translates it.
        $idVisitorLang = (int) $this->context->language->id;
        $idDefaultLang = (int) Configuration::get('PS_LANG_DEFAULT');
        if (!$idDefaultLang) {
            $idDefaultLang = $idVisitorLang;
        }

        $entry->question = [];
        $entry->answer = [];
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $keepQuestion = ($idLang === $idVisitorLang || $idLang === $idDefaultLang);
            $entry->question[$idLang] = $keepQuestion ? $question : '';
            $entry->answer[$idLang] = '';
        }

        if (!$entry->add()) {
            return self::REFUSED;
        }

        $this->module->notifyNewQuestion($entry, $question);

        return self::SENT;
    }

    /**
     * @param int $idProduct
     * @param int $code
     *
     * @return void
     */
    private function finish($idProduct, $code)
    {
        $product = $idProduct ? new Product($idProduct) : null;

        if ($product && Validate::isLoadedObject($product)) {
            $url = $this->context->link->getProductLink($product);
        } else {
            $url = $this->context->link->getPageLink('index', true);
        }

        if ($code) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'mf_sent=' . (int) $code;
        }

        Tools::redirect($url . '#megfaq');
    }
}
