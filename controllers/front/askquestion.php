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
 * @copyright 2019-2026 MEG Venture
 * @license   Academic Free License 3.0 (AFL-3.0)
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
        $idProduct = (int) Tools::getValue('id_product');

        $this->finish($idProduct, $this->handle($idProduct));
    }

    public function initContent()
    {
        // A GET on this route is not a submission. Send them somewhere real.
        $this->finish((int) Tools::getValue('id_product'), 0);
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

        $product = new Product($idProduct);
        if (!Validate::isLoadedObject($product)) {
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

        // The question is stored against the language it was asked in, and only
        // that one. Copying it into the other seven would leave a Polish shopper
        // reading a Turkish question the day it is published.
        $entry->question = [];
        $entry->answer = [];
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $entry->question[$idLang] = $idLang === (int) $this->context->language->id ? $question : '';
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
