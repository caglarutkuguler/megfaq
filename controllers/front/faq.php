<?php
/**
 * Product FAQ and Customer Questions
 *
 * The shop's own FAQ page.
 *
 * One page that carries every published question and answer, grouped: the ones
 * that apply to everything first, then a row per product. It exists because a
 * question answered on a product page is only findable by someone already on
 * that product page - and because a single page is the thing you can hand to a
 * search engine, an answer engine, or a customer, and say "it is all in here".
 *
 * The page can be searched, and the search is answered here, on the server,
 * from ?q=. A visitor without a script gets a page that already shows only what
 * matched; a visitor with one gets the same page, and front.js then filters the
 * same list as they type without another request. Both read the same text and
 * apply the same rule (MegFaqValidator::contains), so they never disagree.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegFaqFaqModuleFrontController extends ModuleFrontController
{
    /**
     * Up to this many matches, every matching entry opens so the answer is on
     * screen. Above it the page would be a wall of open answers, so the matches
     * stay closed and the visitor picks one - or types one more word, which is
     * what they were going to do anyway. front.js applies the same number.
     */
    const OPEN_MATCHES_UP_TO = 10;

    /**
     * Below this many characters nothing is filtered: one letter matches
     * everything, so the page would be filtered and look unfiltered. The same
     * number as front.js, so a page that arrived with ?q=a and the script's
     * own idea of that box agree.
     */
    const MIN_CHARS = 2;

    /** @var string What the visitor typed; empty for the whole page. */
    private $query = '';

    /** @var bool Whether what they typed is long enough to filter by. */
    private $searching = false;

    public function init()
    {
        $this->page_name = 'megfaq-page';

        // Read before parent::initContent() builds the page variables, because
        // getTemplateVarPage() below needs to know whether this is a search.
        $this->query = MegFaqValidator::cleanQuery(Tools::getValue('q'));
        $this->searching = MegFaqValidator::length($this->query) >= self::MIN_CHARS;

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
        $page = $this->group($entries, $idShop, $idLang, (bool) (int) $settings['MEGFAQ_OPEN_FIRST']);

        $this->context->smarty->assign([
            'mf_groups' => $page['groups'],
            'mf_total' => $page['total'],
            'mf_matches' => $page['matches'],
            'mf_query' => $this->query,
            'mf_searching' => $this->searching,
            'mf_page_url' => $this->context->link->getModuleLink('megfaq', 'faq'),
        ]);

        $this->setTemplate('module:megfaq/views/templates/front/faq.tpl');
    }

    /**
     * One address per language, with nothing after it.
     *
     * The core gives module pages no canonical at all, so until now this page
     * had none. A search is a view of this page rather than a page of its own:
     * ?q=... points back here and is kept out of the index below.
     *
     * @return string
     */
    public function getCanonicalURL()
    {
        return $this->context->link->getModuleLink('megfaq', 'faq', [], true);
    }

    /**
     * @return array
     */
    public function getTemplateVarPage()
    {
        $page = parent::getTemplateVarPage();

        if ($this->searching) {
            $page['meta']['robots'] = 'noindex';
        }

        return $page;
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
     * A search typed into the short address travels with the redirect.
     *
     * @return void
     */
    private function redirectToCanonical()
    {
        $canonical = $this->getCanonicalURL();

        $here = parse_url(Tools::getCurrentUrl(), PHP_URL_PATH);
        $there = parse_url($canonical, PHP_URL_PATH);

        if ($here === null || $there === null || rtrim($here, '/') === rtrim($there, '/')) {
            return;
        }

        if ($this->query !== '') {
            $canonical = $this->context->link->getModuleLink('megfaq', 'faq', ['q' => $this->query], true);
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
     * Every entry and every group carries `match` (does it survive the current
     * search) and `open` (does it start expanded). `default_open` is what the
     * row looks like with no search at all; the script keeps it so that clearing
     * the box on a page that arrived filtered restores the page, not the filter.
     *
     * @param array $entries
     * @param int   $idShop
     * @param int   $idLang
     * @param bool  $openFirst
     *
     * @return array{groups: array, total: int, matches: int}
     */
    private function group(array $entries, $idShop, $idLang, $openFirst)
    {
        $names = $this->productNames($entries, $idShop, $idLang);
        $needle = $this->searching ? $this->query : '';
        $groups = [];
        $total = 0;
        $matches = 0;

        foreach ($entries as $row) {
            $idProduct = (int) $row['id_product'];

            // A product that has been deleted since is skipped rather than shown
            // under "Deleted product": the answer is about something a shopper
            // can no longer buy, so the page is better without it.
            if ($idProduct && !isset($names[$idProduct])) {
                continue;
            }

            if (!isset($groups[$idProduct])) {
                $title = $idProduct
                    ? $names[$idProduct]['name']
                    : $this->module->l('Questions about the shop', 'faq');

                $groups[$idProduct] = [
                    'id_product' => $idProduct,
                    'title' => $title,
                    'url' => $idProduct ? $names[$idProduct]['url'] : '',
                    // A product name that matches brings its whole row along:
                    // someone typing the name of a product wants its questions.
                    'title_match' => MegFaqValidator::contains($title, $needle),
                    'matches' => 0,
                    'entries' => [],
                ];
            }

            $match = $groups[$idProduct]['title_match']
                || MegFaqValidator::contains($row['question'], $needle)
                || MegFaqValidator::contains($row['answer'], $needle);

            $groups[$idProduct]['entries'][] = [
                'id' => (int) $row['id_megfaq'],
                'question' => MegFaq::escape($row['question']),
                'answer' => nl2br(MegFaq::escape($row['answer'])),
                'match' => $match,
            ];

            ++$total;

            if ($match) {
                ++$matches;
                ++$groups[$idProduct]['matches'];
            }
        }

        $groups = array_values($groups);
        $searching = $this->searching;
        $openMatches = $searching && $matches <= self::OPEN_MATCHES_UP_TO;

        foreach ($groups as $g => $group) {
            $groups[$g]['match'] = $group['matches'] > 0;
            $groups[$g]['default_open'] = $g === 0;
            $groups[$g]['open'] = $searching ? $group['matches'] > 0 : $g === 0;

            foreach ($group['entries'] as $e => $entry) {
                $defaultOpen = $openFirst && $g === 0 && $e === 0;
                $groups[$g]['entries'][$e]['default_open'] = $defaultOpen;
                $groups[$g]['entries'][$e]['open'] = $searching
                    ? ($openMatches && $entry['match'])
                    : $defaultOpen;
            }
        }

        return ['groups' => $groups, 'total' => $total, 'matches' => $matches];
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
