<?php
/**
 * Product FAQ and Customer Questions
 *
 * A FAQ that a shop with forty products can actually keep filled in.
 *
 * The design follows from one observation: most FAQ modules make you write the
 * same answer once per product. "How many shops does the licence cover?" is the
 * same sentence on every page, and a merchant who has to paste it forty times
 * writes it twice and gives up. So an entry either belongs to one product or to
 * all of them, and the product page shows both sets with the product's own
 * questions first.
 *
 * There is no FAQPage structured data here, and its absence is deliberate rather
 * than an omission. Google stopped showing FAQ rich results on 7 May 2026 and
 * removed the documentation on 15 June 2026. Emitting the markup would cost
 * nothing and do nothing, and a module that advertises a search feature that no
 * longer exists is selling something it cannot deliver. What the module does
 * instead is put the questions and answers in the HTML the server sends, where a
 * shopper, a crawler and an answer engine can all read them.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * WidgetInterface lived in the global namespace on PrestaShop 1.7.x
 * (classes/module/WidgetInterface.php) and moved to
 * PrestaShop\PrestaShop\Core\Module\WidgetInterface on newer core versions,
 * with no global-namespace alias shipped. Without this, the class declaration
 * below fatals on 8.x and 9.x - which the module manager reports as a bare
 * "Server responded with 500 code" and nothing else.
 */
if (!interface_exists('WidgetInterface', false)
    && interface_exists('PrestaShop\\PrestaShop\\Core\\Module\\WidgetInterface')
) {
    class_alias('PrestaShop\\PrestaShop\\Core\\Module\\WidgetInterface', 'WidgetInterface');
}

require_once __DIR__ . '/classes/MegFaqValidator.php';
require_once __DIR__ . '/classes/MegFaqEntry.php';

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class MegFaq extends Module implements WidgetInterface
{
    const HOOKS = [
        'actionFrontControllerSetMedia',
        'actionAdminControllerSetMedia',
        'moduleRoutes',
        'displayFooterProduct',
        'actionDeleteGDPRCustomer',
        'actionExportGDPRData',
    ];

    const SETTINGS = [
        // Where it shows.
        'MEGFAQ_ON_PRODUCT' => 1,
        'MEGFAQ_GLOBAL_ON_PRODUCT' => 1,
        'MEGFAQ_PAGE' => 1,
        'MEGFAQ_OPEN_FIRST' => 1,
        // Questions from shoppers.
        'MEGFAQ_ASK' => 1,
        'MEGFAQ_ASK_WHO' => 'all',
        'MEGFAQ_FLOOD' => 3,
        'MEGFAQ_CONSENT_CMS' => 0,
        // Mail.
        'MEGFAQ_NOTIFY' => 1,
        'MEGFAQ_NOTIFY_EMAIL' => '',
        'MEGFAQ_ANSWER_MAIL' => 1,
        // Back office.
        'MEGFAQ_PER_PAGE' => 20,
    ];

    /** Rows a back office page shows before paging. */
    const PER_PAGE_MAX = 100;

    /** @var string */
    private $html = '';

    public function __construct()
    {
        $this->name = 'megfaq';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'MEG Venture';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->l('Product FAQ and Customer Questions - Shared Answers, Ask a Question and One FAQ Page');
        $this->description = $this->l('Answer the questions that stop people buying, on the page where they stop. Write an answer once and show it on every product, or attach it to one product only. Shoppers can ask their own question from the product page; it lands in a moderation queue, and publishing your answer turns it into a FAQ entry. Everything is written into the page the server sends, so shoppers, crawlers and AI answer engines all read the same text.');
        $this->confirmUninstall = $this->l('Every question and answer will be permanently deleted. Export them first if you want to keep them. Continue?');
    }

    /* ----------------------------------------------------------- lifecycle */

    public function install()
    {
        if (!parent::install() || !$this->installDb()) {
            return false;
        }

        foreach (self::HOOKS as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        foreach (self::SETTINGS as $key => $value) {
            if (!Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        return true;
    }

    public function uninstall()
    {
        foreach (array_keys(self::SETTINGS) as $key) {
            Configuration::deleteByName($key);
        }

        // Language and shop rows first: they carry the foreign keys, and a
        // half-dropped set is harder to explain than a clean one.
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'megfaq_lang`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'megfaq_shop`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'megfaq`');

        return parent::uninstall();
    }

    /**
     * @return bool
     */
    private function installDb()
    {
        $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';

        $sql = [
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megfaq` (
                `id_megfaq` int(10) unsigned NOT NULL AUTO_INCREMENT,
                `id_product` int(10) unsigned NOT NULL DEFAULT 0,
                `id_customer` int(10) unsigned NOT NULL DEFAULT 0,
                `customer_name` varchar(60) NOT NULL DEFAULT \'\',
                `customer_email` varchar(128) NOT NULL DEFAULT \'\',
                `position` int(10) unsigned NOT NULL DEFAULT 0,
                `active` tinyint(1) unsigned NOT NULL DEFAULT 0,
                `date_add` datetime NOT NULL,
                `date_upd` datetime NOT NULL,
                PRIMARY KEY (`id_megfaq`),
                KEY `idx_product` (`id_product`, `active`, `position`),
                KEY `idx_email` (`customer_email`, `date_add`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megfaq_lang` (
                `id_megfaq` int(10) unsigned NOT NULL,
                `id_lang` int(10) unsigned NOT NULL,
                `question` varchar(500) NOT NULL DEFAULT \'\',
                `answer` text,
                PRIMARY KEY (`id_megfaq`, `id_lang`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',

            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'megfaq_shop` (
                `id_megfaq` int(10) unsigned NOT NULL,
                `id_shop` int(10) unsigned NOT NULL,
                PRIMARY KEY (`id_megfaq`, `id_shop`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;',
        ];

        foreach ($sql as $statement) {
            if (!Db::getInstance()->execute($statement)) {
                return false;
            }
        }

        return true;
    }

    /* -------------------------------------------------------------- shared */

    /**
     * @return array<string, mixed>
     */
    public function getSettings()
    {
        $out = [];
        foreach (self::SETTINGS as $key => $default) {
            $value = Configuration::get($key);
            $out[$key] = ($value === false || $value === null) ? $default : $value;
        }

        return $out;
    }

    /**
     * @return int
     */
    private function getShopId()
    {
        return (int) $this->context->shop->id;
    }

    /**
     * @return int
     */
    private function getLangId()
    {
        return (int) $this->context->language->id;
    }

    /**
     * @param string $controller
     * @param array  $params
     *
     * @return string
     */
    public function frontUrl($controller, array $params = [])
    {
        return $this->context->link->getModuleLink($this->name, $controller, $params, true);
    }

    /* --------------------------------------------------------------- hooks */

    public function hookModuleRoutes()
    {
        return [
            'module-megfaq-faq' => [
                'controller' => 'faq',
                'rule' => 'faq',
                'keywords' => [],
                'params' => ['fc' => 'module', 'module' => $this->name, 'controller' => 'faq'],
            ],
        ];
    }

    public function hookActionFrontControllerSetMedia()
    {
        $this->context->controller->registerStylesheet(
            'megfaq-front',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 150]
        );

        $this->context->controller->registerJavascript(
            'megfaq-front',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 150]
        );
    }

    public function hookActionAdminControllerSetMedia()
    {
        if (Tools::getValue('configure') !== $this->name) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');
    }

    /**
     * The FAQ block under the product.
     *
     * displayFooterProduct rather than displayProductAdditionalInfo: the block is
     * a list of prose, it belongs below the description, and the additional-info
     * slot sits beside the price where it would push the buy button down.
     */
    public function hookDisplayFooterProduct($params)
    {
        try {
            $settings = $this->getSettings();

            if (!(int) $settings['MEGFAQ_ON_PRODUCT']) {
                return '';
            }

            $idProduct = $this->currentProductId($params);
            if (!$idProduct) {
                return '';
            }

            $entries = MegFaqEntry::getForProduct(
                $idProduct,
                $this->getShopId(),
                $this->getLangId(),
                (bool) $settings['MEGFAQ_GLOBAL_ON_PRODUCT']
            );

            $canAsk = $this->canAsk($settings);

            // Nothing to show and nothing to offer is not an empty block, it is
            // no block at all. An empty "Questions" heading on a product page
            // reads as a broken module.
            if (!$entries && !$canAsk) {
                return '';
            }

            $this->smarty->assign([
                'mf_entries' => $this->presentEntries($entries),
                'mf_id_product' => $idProduct,
                'mf_can_ask' => $canAsk,
                'mf_ask_url' => $this->frontUrl('askquestion'),
                'mf_page_url' => (int) $settings['MEGFAQ_PAGE'] ? $this->frontUrl('faq') : '',
                'mf_open_first' => (int) $settings['MEGFAQ_OPEN_FIRST'],
                'mf_consent' => $this->consentText($settings),
                'mf_prefill' => $this->askPrefill(),
                'mf_notice' => $this->frontNotice(),
            ]);

            return $this->display(__FILE__, 'views/templates/hook/product-faq.tpl');
        } catch (Throwable $e) {
            $this->logError($e);

            return '';
        }
    }

    /**
     * Widget interface, so a theme can drop the FAQ list into a template or a
     * merchant can place it with {widget name='megfaq'}.
     *
     * @param string $hookName
     * @param array  $params
     *
     * @return string
     */
    public function renderWidget($hookName, array $params)
    {
        $variables = $this->getWidgetVariables($hookName, $params);

        if (!$variables['mf_entries']) {
            return '';
        }

        $this->smarty->assign($variables);

        return $this->display(__FILE__, 'views/templates/hook/product-faq.tpl');
    }

    /**
     * @param string $hookName
     * @param array  $params
     *
     * @return array
     */
    public function getWidgetVariables($hookName, array $params)
    {
        $settings = $this->getSettings();
        $idProduct = $this->currentProductId($params);

        $entries = $idProduct
            ? MegFaqEntry::getForProduct(
                $idProduct,
                $this->getShopId(),
                $this->getLangId(),
                (bool) $settings['MEGFAQ_GLOBAL_ON_PRODUCT']
            )
            : MegFaqEntry::getAll($this->getShopId(), $this->getLangId());

        return [
            'mf_entries' => $this->presentEntries($entries),
            'mf_id_product' => $idProduct,
            'mf_can_ask' => $idProduct ? $this->canAsk($settings) : false,
            'mf_ask_url' => $this->frontUrl('askquestion'),
            'mf_page_url' => (int) $settings['MEGFAQ_PAGE'] ? $this->frontUrl('faq') : '',
            'mf_open_first' => (int) $settings['MEGFAQ_OPEN_FIRST'],
            'mf_consent' => $this->consentText($settings),
            'mf_prefill' => $this->askPrefill(),
            'mf_notice' => '',
        ];
    }

    /* ----------------------------------------------------------------- GDPR */

    /**
     * A question carries a name and an address, so it is personal data and the
     * shop has to be able to hand it over and to erase it.
     *
     * Erasure blanks the asker rather than deleting the row: the answer is the
     * shop's own writing and other shoppers are relying on it. What leaves is
     * everything that points at a person.
     */
    public function hookActionDeleteGDPRCustomer($customer)
    {
        $email = isset($customer['email']) ? (string) $customer['email'] : '';

        if ($email === '' || !Validate::isEmail($email)) {
            return json_encode(true);
        }

        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'megfaq`'
            . " SET `customer_name` = '', `customer_email` = '', `id_customer` = 0"
            . " WHERE `customer_email` = '" . pSQL($email) . "'"
        );

        return json_encode(true);
    }

    public function hookActionExportGDPRData($customer)
    {
        $email = isset($customer['email']) ? (string) $customer['email'] : '';

        if ($email === '' || !Validate::isEmail($email)) {
            return json_encode([]);
        }

        $rows = Db::getInstance()->executeS(
            'SELECT f.`id_megfaq`, f.`id_product`, f.`customer_name`, f.`date_add`, fl.`question`'
            . ' FROM `' . _DB_PREFIX_ . 'megfaq` f'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'megfaq_lang` fl'
            . ' ON fl.`id_megfaq` = f.`id_megfaq` AND fl.`id_lang` = ' . $this->getLangId()
            . " WHERE f.`customer_email` = '" . pSQL($email) . "'"
        );

        return json_encode(is_array($rows) ? $rows : []);
    }

    /* ------------------------------------------------------------- helpers */

    /**
     * @param array $settings
     *
     * @return bool
     */
    private function canAsk(array $settings)
    {
        if (!(int) $settings['MEGFAQ_ASK']) {
            return false;
        }

        if ($settings['MEGFAQ_ASK_WHO'] === 'customers') {
            return (bool) $this->context->customer->isLogged();
        }

        return true;
    }

    /**
     * Name and address for a signed-in customer, so they do not retype what the
     * shop already knows.
     *
     * @return array{name: string, email: string}
     */
    private function askPrefill()
    {
        $customer = $this->context->customer;

        if (!$customer || !$customer->isLogged()) {
            return ['name' => '', 'email' => ''];
        }

        return [
            'name' => trim($customer->firstname . ' ' . $customer->lastname),
            'email' => (string) $customer->email,
        ];
    }

    /**
     * @param array $settings
     *
     * @return array{text: string, url: string}|null
     */
    private function consentText(array $settings)
    {
        $idCms = (int) $settings['MEGFAQ_CONSENT_CMS'];

        if (!$idCms) {
            return null;
        }

        $cms = new CMS($idCms, $this->getLangId());

        if (!Validate::isLoadedObject($cms)) {
            return null;
        }

        return [
            'text' => (string) $cms->meta_title,
            'url' => $this->context->link->getCMSLink($cms),
        ];
    }

    /**
     * The one-line result of a submission, carried back on the redirect rather
     * than in the session so a refresh cannot replay it.
     *
     * @return string
     */
    private function frontNotice()
    {
        $code = (int) Tools::getValue('mf_sent');

        switch ($code) {
            case 1:
                return $this->l('Thank you - your question has been sent. We will answer it here, and by e-mail if you left an address.');
            case 2:
                return $this->l('That question could not be sent. Please check the fields and try again.');
            case 3:
                return $this->l('You have asked several questions already. Please wait a little before sending another.');
            default:
                return '';
        }
    }

    /**
     * Escape once, here, rather than in the template where a missed |escape is
     * invisible until it is not.
     *
     * @param array $entries
     *
     * @return array
     */
    private function presentEntries(array $entries)
    {
        $out = [];

        foreach ($entries as $row) {
            $out[] = [
                'id' => (int) $row['id_megfaq'],
                'id_product' => (int) $row['id_product'],
                'question' => self::escape($row['question']),
                // nl2br after escaping: the answer is stored as plain text with
                // paragraph breaks, and the breaks are the only markup it gets.
                'answer' => nl2br(self::escape($row['answer'])),
                'is_global' => (int) $row['id_product'] === 0,
            ];
        }

        return $out;
    }

    /**
     * Escape once, on the way out.
     *
     * Not Tools::htmlentitiesUTF8(): deprecated since PrestaShop 8.0, and it
     * encodes every character it can rather than the five that matter, which
     * turns a Turkish or Polish answer into a wall of entities.
     *
     * @param string $value
     *
     * @return string
     */
    public static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array $params
     *
     * @return int
     */
    private function currentProductId($params)
    {
        if (isset($params['product'])) {
            $product = $params['product'];

            if (is_object($product) && isset($product->id)) {
                return (int) $product->id;
            }

            if (is_array($product) && isset($product['id_product'])) {
                return (int) $product['id_product'];
            }
        }

        if (isset($params['id_product'])) {
            return (int) $params['id_product'];
        }

        if ($this->context->controller instanceof ProductController) {
            return (int) Tools::getValue('id_product');
        }

        return 0;
    }

    /**
     * @param Throwable $e
     *
     * @return void
     */
    public function logError($e)
    {
        PrestaShopLogger::addLog(
            'megfaq: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            3, null, 'MegFaq'
        );
    }

    /* ---------------------------------------------------------------- mail */

    /**
     * Tell the shop a question has arrived.
     *
     * @param MegFaqEntry $entry
     * @param string      $question
     *
     * @return void
     */
    public function notifyNewQuestion(MegFaqEntry $entry, $question)
    {
        $settings = $this->getSettings();

        if (!(int) $settings['MEGFAQ_NOTIFY']) {
            return;
        }

        $to = trim((string) $settings['MEGFAQ_NOTIFY_EMAIL']);
        if ($to === '' || !Validate::isEmail($to)) {
            $to = (string) Configuration::get('PS_SHOP_EMAIL');
        }

        if (!Validate::isEmail($to)) {
            return;
        }

        $product = $entry->id_product ? new Product((int) $entry->id_product, false, $this->getLangId()) : null;

        $this->send('megfaq_new', $to, $this->l('A customer asked a question'), [
            '{question}' => $question,
            '{asked_by}' => $entry->customer_name !== '' ? $entry->customer_name : $this->l('a guest'),
            '{product}' => ($product && Validate::isLoadedObject($product))
                ? $product->name
                : $this->l('No particular product'),
            '{answer_url}' => $this->context->link->getAdminLink('AdminModules', true)
                . '&configure=' . $this->name,
        ]);
    }

    /**
     * Tell the shopper their question has been answered.
     *
     * @param MegFaqEntry $entry
     * @param string      $question
     * @param string      $answer
     *
     * @return void
     */
    public function notifyAnswered(MegFaqEntry $entry, $question, $answer)
    {
        $settings = $this->getSettings();

        if (!(int) $settings['MEGFAQ_ANSWER_MAIL']) {
            return;
        }

        $to = trim((string) $entry->customer_email);

        if ($to === '' || !Validate::isEmail($to)) {
            return;
        }

        $product = $entry->id_product ? new Product((int) $entry->id_product, false, $this->getLangId()) : null;

        $this->send('megfaq_answered', $to, $this->l('Your question has been answered'), [
            '{question}' => $question,
            '{answer}' => $answer,
            '{name}' => $entry->customer_name,
            '{product_url}' => ($product && Validate::isLoadedObject($product))
                ? $this->context->link->getProductLink($product)
                : $this->frontUrl('faq'),
        ]);
    }

    /**
     * @param string $template
     * @param string $to
     * @param string $subject
     * @param array  $vars
     *
     * @return void
     */
    private function send($template, $to, $subject, array $vars)
    {
        try {
            Mail::Send(
                $this->getLangId(),
                $template,
                $subject,
                $vars,
                $to,
                null,
                null,
                null,
                null,
                null,
                dirname(__FILE__) . '/mails/',
                false,
                $this->getShopId()
            );
        } catch (Throwable $e) {
            // A shop with no working mail transport must still be able to take
            // a question. The row is saved before this runs.
            $this->logError($e);
        }
    }
    /* ---------------------------------------------------------------- admin */

    public function getContent()
    {
        $this->postProcess();

        $settings = $this->getSettings();
        $idLang = $this->getLangId();

        $tab = Tools::getValue('mf_tab', 'list');
        if (!in_array($tab, ['list', 'settings', 'help'], true)) {
            $tab = 'list';
        }

        $filters = [
            'status' => Tools::getValue('mf_status', ''),
            'scope' => Tools::getValue('mf_scope', ''),
            'search' => trim((string) Tools::getValue('mf_search', '')),
        ];

        $perPage = min(self::PER_PAGE_MAX, max(5, (int) $settings['MEGFAQ_PER_PAGE']));
        $total = MegFaqEntry::countSearch($filters, $idLang);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($pages, max(1, (int) Tools::getValue('mf_page', 1)));

        $rows = MegFaqEntry::search($filters, $idLang, $perPage, ($page - 1) * $perPage);

        $this->smarty->assign([
            'mf_tab' => $tab,
            'mf_module_name' => $this->displayName,
            'mf_version' => $this->version,
            'mf_settings' => $settings,
            'mf_filters' => $filters,
            'mf_rows' => $this->presentAdminRows($rows),
            'mf_total' => $total,
            'mf_page' => $page,
            'mf_pages' => $pages,
            'mf_pending' => MegFaqEntry::countPending($idLang),
            'mf_published' => MegFaqEntry::countSearch(['status' => 'published'], $idLang),
            'mf_globals' => MegFaqEntry::countSearch(['scope' => 'global'], $idLang),
            'mf_languages' => $this->languageChoices(),
            'mf_edit' => $this->editing(),
            'mf_form_url' => $this->configUrl(),
            'mf_page_url' => $this->frontUrl('faq'),
            'mf_cms_pages' => $this->cmsPageChoices(),
            'mf_who_choices' => [
                'all' => $this->l('Anyone'),
                'customers' => $this->l('Signed-in customers only'),
            ],
            'mf_html' => $this->html,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/configure.tpl');
    }

    /**
     * @param array $params
     *
     * @return string
     */
    private function configUrl(array $params = [])
    {
        $base = $this->context->link->getAdminLink('AdminModules', true)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;

        foreach ($params as $key => $value) {
            $base .= '&' . $key . '=' . urlencode((string) $value);
        }

        return $base;
    }

    /**
     * @return void
     */
    private function postProcess()
    {
        try {
            if (Tools::isSubmit('submitMegFaqSettings')) {
                $this->saveSettings();
            } elseif (Tools::isSubmit('submitMegFaqEntry')) {
                $this->saveEntry();
            } elseif (Tools::isSubmit('submitMegFaqDelete')) {
                $this->deleteEntry((int) Tools::getValue('mf_id'));
            } elseif (Tools::isSubmit('submitMegFaqToggle')) {
                $this->toggleEntry((int) Tools::getValue('mf_id'));
            }
        } catch (Throwable $e) {
            $this->logError($e);
            $this->html .= $this->displayError($this->l('Something went wrong. The details are in the shop logs.'));
        }
    }

    /**
     * @return void
     */
    private function saveSettings()
    {
        $email = trim((string) Tools::getValue('MEGFAQ_NOTIFY_EMAIL'));

        if ($email !== '' && !Validate::isEmail($email)) {
            $this->html .= $this->displayError($this->l('That notification address is not a valid e-mail address.'));

            return;
        }

        $who = Tools::getValue('MEGFAQ_ASK_WHO');
        if (!in_array($who, ['all', 'customers'], true)) {
            $who = 'all';
        }

        Configuration::updateValue('MEGFAQ_ON_PRODUCT', (int) Tools::getValue('MEGFAQ_ON_PRODUCT'));
        Configuration::updateValue('MEGFAQ_GLOBAL_ON_PRODUCT', (int) Tools::getValue('MEGFAQ_GLOBAL_ON_PRODUCT'));
        Configuration::updateValue('MEGFAQ_PAGE', (int) Tools::getValue('MEGFAQ_PAGE'));
        Configuration::updateValue('MEGFAQ_OPEN_FIRST', (int) Tools::getValue('MEGFAQ_OPEN_FIRST'));
        Configuration::updateValue('MEGFAQ_ASK', (int) Tools::getValue('MEGFAQ_ASK'));
        Configuration::updateValue('MEGFAQ_ASK_WHO', $who);
        Configuration::updateValue('MEGFAQ_FLOOD', max(0, (int) Tools::getValue('MEGFAQ_FLOOD')));
        Configuration::updateValue('MEGFAQ_CONSENT_CMS', (int) Tools::getValue('MEGFAQ_CONSENT_CMS'));
        Configuration::updateValue('MEGFAQ_NOTIFY', (int) Tools::getValue('MEGFAQ_NOTIFY'));
        Configuration::updateValue('MEGFAQ_NOTIFY_EMAIL', $email);
        Configuration::updateValue('MEGFAQ_ANSWER_MAIL', (int) Tools::getValue('MEGFAQ_ANSWER_MAIL'));
        Configuration::updateValue('MEGFAQ_PER_PAGE', min(self::PER_PAGE_MAX, max(5, (int) Tools::getValue('MEGFAQ_PER_PAGE'))));

        $this->html .= $this->displayConfirmation($this->l('Settings saved.'));
    }

    /**
     * Create or update one entry, in every language the form carried.
     *
     * A language left blank is stored blank rather than skipped, so clearing a
     * translation actually clears it - and a blank answer means "not published
     * in this language", which is the rule the front office reads.
     *
     * @return void
     */
    private function saveEntry()
    {
        $id = (int) Tools::getValue('mf_id');
        $entry = $id ? new MegFaqEntry($id) : new MegFaqEntry();
        $isNew = !$id || !Validate::isLoadedObject($entry);

        if ($isNew) {
            $entry = new MegFaqEntry();
        }

        $idProduct = (int) Tools::getValue('mf_id_product');
        if ($idProduct) {
            $product = new Product($idProduct);
            if (!Validate::isLoadedObject($product)) {
                $this->html .= $this->displayError($this->l('There is no product with that id.'));

                return;
            }
        }

        $questions = (array) Tools::getValue('mf_question', []);
        $answers = (array) Tools::getValue('mf_answer', []);
        $shopLang = (int) Configuration::get('PS_LANG_DEFAULT');

        if (MegFaqValidator::isBlank(isset($questions[$shopLang]) ? $questions[$shopLang] : '')) {
            $this->html .= $this->displayError($this->l('An entry needs a question in the shop default language.'));

            return;
        }

        $wasUnanswered = !$isNew && $this->answerIsEmpty($entry, $shopLang);

        $entry->id_product = $idProduct;
        $entry->active = (bool) Tools::getValue('mf_active');
        $entry->position = (int) Tools::getValue('mf_position', $isNew ? MegFaqEntry::nextPosition($idProduct) : $entry->position);

        $entry->question = [];
        $entry->answer = [];
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $entry->question[$idLang] = Tools::substr(
                MegFaqValidator::cleanLine(isset($questions[$idLang]) ? $questions[$idLang] : ''),
                0,
                MegFaqValidator::QUESTION_MAX
            );
            $entry->answer[$idLang] = Tools::substr(
                MegFaqValidator::cleanText(isset($answers[$idLang]) ? $answers[$idLang] : ''),
                0,
                MegFaqValidator::ANSWER_MAX
            );
        }

        $saved = $isNew ? $entry->add() : $entry->update();

        if (!$saved) {
            $this->html .= $this->displayError($this->l('That entry could not be saved.'));

            return;
        }

        // The shopper hears back the moment their question stops being a
        // question - not when it was received, which tells them nothing.
        if ($wasUnanswered
            && $entry->active
            && !MegFaqValidator::isBlank($entry->answer[$shopLang])
        ) {
            $this->notifyAnswered($entry, $entry->question[$shopLang], $entry->answer[$shopLang]);
        }

        $this->html .= $this->displayConfirmation($isNew ? $this->l('Entry added.') : $this->l('Entry saved.'));
    }

    /**
     * @param MegFaqEntry $entry
     * @param int         $idLang
     *
     * @return bool
     */
    private function answerIsEmpty(MegFaqEntry $entry, $idLang)
    {
        $answer = is_array($entry->answer)
            ? (isset($entry->answer[$idLang]) ? $entry->answer[$idLang] : '')
            : $entry->answer;

        return MegFaqValidator::isBlank($answer);
    }

    /**
     * @param int $id
     *
     * @return void
     */
    private function deleteEntry($id)
    {
        $entry = new MegFaqEntry($id);

        if (!Validate::isLoadedObject($entry) || !$entry->delete()) {
            $this->html .= $this->displayError($this->l('That entry could not be deleted.'));

            return;
        }

        $this->html .= $this->displayConfirmation($this->l('Entry deleted.'));
    }

    /**
     * @param int $id
     *
     * @return void
     */
    private function toggleEntry($id)
    {
        $entry = new MegFaqEntry($id);

        if (!Validate::isLoadedObject($entry)) {
            return;
        }

        $shopLang = (int) Configuration::get('PS_LANG_DEFAULT');

        if (!$entry->active && $this->answerIsEmpty($entry, $shopLang)) {
            $this->html .= $this->displayError($this->l('Write an answer before publishing this question.'));

            return;
        }

        $wasUnanswered = !$entry->active;
        $entry->active = !$entry->active;

        if (!$entry->update()) {
            $this->html .= $this->displayError($this->l('That entry could not be saved.'));

            return;
        }

        if ($wasUnanswered && $entry->active) {
            $question = is_array($entry->question) ? $entry->question[$shopLang] : $entry->question;
            $answer = is_array($entry->answer) ? $entry->answer[$shopLang] : $entry->answer;
            $this->notifyAnswered($entry, $question, $answer);
        }

        $this->html .= $this->displayConfirmation($entry->active
            ? $this->l('Published.')
            : $this->l('Unpublished.'));
    }

    /**
     * The entry the form is editing, with one question/answer pair per language.
     *
     * @return array|null
     */
    private function editing()
    {
        $id = (int) Tools::getValue('mf_edit');

        if (!$id) {
            return null;
        }

        $entry = new MegFaqEntry($id);

        if (!Validate::isLoadedObject($entry)) {
            return null;
        }

        $text = [];
        foreach (Language::getLanguages(false) as $language) {
            $idLang = (int) $language['id_lang'];
            $text[$idLang] = [
                'question' => isset($entry->question[$idLang]) ? $entry->question[$idLang] : '',
                'answer' => isset($entry->answer[$idLang]) ? $entry->answer[$idLang] : '',
            ];
        }

        return [
            'id' => (int) $entry->id,
            'id_product' => (int) $entry->id_product,
            'position' => (int) $entry->position,
            'active' => (bool) $entry->active,
            'customer_name' => $entry->customer_name,
            'text' => $text,
        ];
    }

    /**
     * @return array
     */
    private function languageChoices()
    {
        $out = [];

        foreach (Language::getLanguages(false) as $language) {
            $out[] = [
                'id_lang' => (int) $language['id_lang'],
                'name' => $language['name'],
                'iso_code' => Tools::strtoupper($language['iso_code']),
                'is_default' => (int) $language['id_lang'] === (int) Configuration::get('PS_LANG_DEFAULT'),
            ];
        }

        return $out;
    }

    /**
     * @return array
     */
    private function cmsPageChoices()
    {
        $pages = CMS::getCMSPages($this->getLangId(), null, true, $this->getShopId());

        $out = [];
        foreach (is_array($pages) ? $pages : [] as $page) {
            $out[] = ['id' => (int) $page['id_cms'], 'name' => $page['meta_title']];
        }

        return $out;
    }

    /**
     * One product name per row, in one query rather than one per row.
     *
     * @param array $rows
     *
     * @return array
     */
    private function presentAdminRows(array $rows)
    {
        $names = $this->productNames($rows);
        $out = [];

        foreach ($rows as $row) {
            $idProduct = (int) $row['id_product'];
            $answer = (string) $row['answer'];

            $out[] = [
                'id' => (int) $row['id_megfaq'],
                'id_product' => $idProduct,
                'product' => $idProduct
                    ? (isset($names[$idProduct]) ? $names[$idProduct] : $this->l('Deleted product'))
                    : $this->l('Every product'),
                'is_global' => $idProduct === 0,
                'question' => (string) $row['question'],
                'answer' => $answer,
                'answered' => !MegFaqValidator::isBlank($answer),
                'active' => (bool) $row['active'],
                'asked_by' => (string) $row['customer_name'],
                'from_customer' => trim((string) $row['customer_name']) !== '',
                'date_add' => (string) $row['date_add'],
                'edit_url' => $this->configUrl(['mf_edit' => (int) $row['id_megfaq']]),
            ];
        }

        return $out;
    }

    /**
     * @param array $rows
     *
     * @return array<int, string>
     */
    private function productNames(array $rows)
    {
        $ids = [];
        foreach ($rows as $row) {
            if ((int) $row['id_product']) {
                $ids[(int) $row['id_product']] = (int) $row['id_product'];
            }
        }

        if (!$ids) {
            return [];
        }

        $found = Db::getInstance()->executeS(
            'SELECT `id_product`, `name` FROM `' . _DB_PREFIX_ . 'product_lang`'
            . ' WHERE `id_lang` = ' . $this->getLangId()
            . ' AND `id_shop` = ' . $this->getShopId()
            . ' AND `id_product` IN (' . implode(',', $ids) . ')'
        );

        $names = [];
        foreach (is_array($found) ? $found : [] as $row) {
            $names[(int) $row['id_product']] = (string) $row['name'];
        }

        return $names;
    }
}
