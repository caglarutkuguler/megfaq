<?php
/**
 * Product FAQ and Customer Questions
 *
 * The shop's own FAQ page.
 *
 * One page that carries every published question and answer, grouped: the ones
 * that apply to everything first, then a section per product. It exists because
 * a question answered on a product page is only findable by someone already on
 * that product page - and because a single page is the thing you can hand to a
 * search engine, an answer engine, or a customer, and say "it is all in here".
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegFaqFaqModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        $this->page_name = 'megfaq-page';
        parent::init();
    }

    public function initContent()
    {
        parent::initContent();

        $settings = $this->module->getSettings();

        if (!(int) $settings['MEGFAQ_PAGE']) {
            // Switched off is not "empty page", it is "no such page". Anything
            // else leaves a thin, indexable URL behind for as long as the shop
            // has the module installed.
            Tools::redirect($this->context->link->getPageLink('index', true));
        }

        $this->redirectToCanonical();

        $idShop = (int) $this->context->shop->id;
        $idLang = (int) $this->context->language->id;

        $fallback = (int) $settings['MEGFAQ_FALLBACK']
            ? (int) Configuration::get('PS_LANG_DEFAULT')
            : 0;

        $entries = MegFaqEntry::getAll($idShop, $idLang, $fallback);

        $this->context->smarty->assign([
            'mf_groups' => $this->group($entries, $idShop, $idLang),
            'mf_total' => count($entries),
            'mf_open_first' => (int) $settings['MEGFAQ_OPEN_FIRST'],
        ]);

        $this->setTemplate('module:megfaq/views/templates/front/faq.tpl');
    }

    /**
     * Send /faq to the address this language actually owns.
     *
     * The short URL works on its own - PrestaShop's dispatcher matches the route
     * with or without the language prefix and renders in whatever language the
     * visitor is browsing in. That is convenient and, left alone, quietly
     * damaging: the same page would answer at /faq and at /en/faq, and a crawler
     * arriving at /faq carries no language cookie, so it would only ever see the
     * default language there. Eight of the nine translations would be reachable
     * only through the prefixed address, while the unprefixed one competed with
     * one of them for the same content.
     *
     * So /faq keeps working as a doorway - type it, share it, print it - and
     * hands the visitor straight to their own language's page. One address per
     * language, each indexable, each hreflang-able, and nothing duplicated.
     *
     * The comparison is against the canonical link rather than a hardcoded
     * prefix, so a shop with friendly URLs switched off - where the canonical is
     * index.php?fc=module&... and carries no prefix at all - matches on the first
     * request and never redirects.
     *
     * @return void
     */
    private function redirectToCanonical()
    {
        $canonical = $this->context->link->getModuleLink('megfaq', 'faq', [], true);

        $here = parse_url(Tools::getCurrentUrl(), PHP_URL_PATH);
        $there = parse_url($canonical, PHP_URL_PATH);

        if ($here === null || $there === null || rtrim($here, '/') === rtrim($there, '/')) {
            return;
        }

        // Tools::redirect() sends a plain 302. That is the right one here rather
        // than a 301: a permanent redirect is cached by browsers for months and
        // cannot be taken back, and this is a shop preference a merchant may
        // well want to reverse.
        Tools::redirect($canonical);
    }

    /**
     * Shared answers first, then one group per product, each with its name and a
     * link back to the product.
     *
     * @param array $entries
     * @param int   $idShop
     * @param int   $idLang
     *
     * @return array
     */
    private function group(array $entries, $idShop, $idLang)
    {
        $names = $this->productNames($entries, $idShop, $idLang);
        $groups = [];

        foreach ($entries as $row) {
            $idProduct = (int) $row['id_product'];

            // A product that has been deleted since is skipped rather than shown
            // under "Deleted product": the answer is about something a shopper
            // can no longer buy, so the page is better without it.
            if ($idProduct && !isset($names[$idProduct])) {
                continue;
            }

            if (!isset($groups[$idProduct])) {
                $groups[$idProduct] = [
                    'id_product' => $idProduct,
                    'title' => $idProduct
                        ? $names[$idProduct]['name']
                        : $this->module->l('Questions about the shop', 'faq'),
                    'url' => $idProduct ? $names[$idProduct]['url'] : '',
                    'entries' => [],
                ];
            }

            $groups[$idProduct]['entries'][] = [
                'id' => (int) $row['id_megfaq'],
                'question' => MegFaq::escape($row['question']),
                'answer' => nl2br(MegFaq::escape($row['answer'])),
            ];
        }

        return array_values($groups);
    }

    /**
     * @param array $entries
     * @param int   $idShop
     * @param int   $idLang
     *
     * @return array<int, array{name: string, url: string}>
     */
    private function productNames(array $entries, $idShop, $idLang)
    {
        $ids = [];
        foreach ($entries as $row) {
            if ((int) $row['id_product']) {
                $ids[(int) $row['id_product']] = (int) $row['id_product'];
            }
        }

        if (!$ids) {
            return [];
        }

        $rows = Db::getInstance()->executeS(
            'SELECT pl.`id_product`, pl.`name`, pl.`link_rewrite`'
            . ' FROM `' . _DB_PREFIX_ . 'product_lang` pl'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps'
            . ' ON ps.`id_product` = pl.`id_product` AND ps.`id_shop` = ' . (int) $idShop
            . ' WHERE pl.`id_lang` = ' . (int) $idLang
            . ' AND pl.`id_shop` = ' . (int) $idShop
            . ' AND ps.`active` = 1'
            . ' AND pl.`id_product` IN (' . implode(',', $ids) . ')'
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = (int) $row['id_product'];
            $out[$id] = [
                'name' => (string) $row['name'],
                'url' => $this->context->link->getProductLink($id, $row['link_rewrite']),
            ];
        }

        return $out;
    }

    public function getBreadcrumbLinks()
    {
        $breadcrumb = parent::getBreadcrumbLinks();

        $breadcrumb['links'][] = [
            'title' => $this->module->l('Frequently asked questions', 'faq'),
            'url' => $this->context->link->getModuleLink('megfaq', 'faq'),
        ];

        return $breadcrumb;
    }
}
