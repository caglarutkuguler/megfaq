<?php
/**
 * Product FAQ and Customer Questions
 *
 * The FAQ page controller, against stubs - no PrestaShop, no database.
 *
 * What is worth testing here is the shape of the page the controller hands the
 * template: which rows exist, which of them survive a search, which start
 * open, and what the page tells a search engine. Every one of those is a rule
 * a visitor meets before reading a single answer, and every one is invisible
 * from the back office - a wrong open flag or a search that skips answers
 * renders as a page that works and is simply missing something.
 *
 * Run: php tests/FaqPageTest.php
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (!defined('_PS_VERSION_')) {
    if (PHP_SAPI !== 'cli') {
        exit;
    }
    define('_PS_VERSION_', '9.1.4');
}
define('_DB_PREFIX_', 'ps_');

/* -------------------------------------------------------------------- stubs */

class ModuleFrontController
{
    public $module;
    public $context;
    public $page_name = '';
    public $template = '';

    public function init()
    {
    }

    public function initContent()
    {
    }

    public function getTemplateVarPage()
    {
        return [
            'title' => '',
            'canonical' => $this->getCanonicalURL(),
            'meta' => ['robots' => 'index'],
        ];
    }

    public function getCanonicalURL()
    {
        return '';
    }

    public function getBreadcrumbLinks()
    {
        return ['links' => [['title' => 'Home', 'url' => 'https://shop.test/en/']]];
    }

    public function setTemplate($template)
    {
        $this->template = $template;
    }
}

class MegFaqTestRedirect extends Exception
{
}

class Tools
{
    /** @var array */
    public static $values = [];

    /** @var string */
    public static $url = 'https://shop.test/en/faq';

    public static function getValue($key, $default = false)
    {
        return array_key_exists($key, self::$values) ? self::$values[$key] : $default;
    }

    public static function getCurrentUrl()
    {
        return self::$url;
    }

    public static function redirect($url)
    {
        throw new MegFaqTestRedirect($url);
    }
}

class Configuration
{
    public static function get($key)
    {
        return $key === 'PS_LANG_DEFAULT' ? 1 : false;
    }
}

class Db
{
    /** @var array Rows the product-name query answers with. */
    public static $products = [];

    public static function getInstance()
    {
        return new self();
    }

    public function executeS($sql)
    {
        return self::$products;
    }
}

class MegFaqEntry
{
    /** @var array What getAll() returns, in the order the real query sorts. */
    public static $rows = [];

    public static function getAll($idShop, $idLang, $fallback = 0)
    {
        return self::$rows;
    }
}

class MegFaq
{
    public static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

class MegFaqTestLink
{
    public function getModuleLink($module, $controller, array $params = [], $ssl = null, $idLang = null, $idShop = null)
    {
        $url = 'https://shop.test/en/' . $controller;

        return $params ? $url . '?' . http_build_query($params) : $url;
    }

    public function getPageLink($page, $ssl = null)
    {
        return 'https://shop.test/en/';
    }

    public function getProductLink($id, $rewrite = null)
    {
        return 'https://shop.test/en/' . (int) $id . '-' . $rewrite . '.html';
    }
}

class MegFaqTestSmarty
{
    /** @var array */
    public $vars = [];

    public function assign($name, $value = null)
    {
        if (is_array($name)) {
            $this->vars = array_merge($this->vars, $name);
        } else {
            $this->vars[$name] = $value;
        }
    }
}

class MegFaqTestModule
{
    /** @var array */
    public $settings = ['MEGFAQ_PAGE' => 1, 'MEGFAQ_FALLBACK' => 1, 'MEGFAQ_OPEN_FIRST' => 1];

    public function getSettings()
    {
        return $this->settings;
    }

    public function l($string, $source = null)
    {
        return $string;
    }
}

require_once __DIR__ . '/../classes/MegFaqValidator.php';
require_once __DIR__ . '/../controllers/front/faq.php';

/* ------------------------------------------------------------------ harness */

$passed = 0;
$failed = 0;

function check($label, $condition)
{
    global $passed, $failed;

    if ($condition) {
        ++$passed;

        return;
    }

    ++$failed;
    echo '  FAIL: ' . $label . PHP_EOL;
}

/**
 * Run the controller the way the dispatcher would, and hand back what it gave
 * the template - or where it sent the visitor instead.
 *
 * @param array  $get
 * @param string $url
 * @param array  $settings
 *
 * @return array{controller: MegFaqFaqModuleFrontController, vars: array, redirect: string|null}
 */
function page(array $get = [], $url = 'https://shop.test/en/faq', array $settings = [])
{
    Tools::$values = $get;
    Tools::$url = $url;

    $controller = new MegFaqFaqModuleFrontController();
    $controller->module = new MegFaqTestModule();
    $controller->module->settings = array_merge($controller->module->settings, $settings);
    $controller->context = (object) [
        'shop' => (object) ['id' => 1],
        'language' => (object) ['id' => 1],
        'link' => new MegFaqTestLink(),
        'smarty' => new MegFaqTestSmarty(),
    ];

    try {
        $controller->init();
        $controller->initContent();
    } catch (MegFaqTestRedirect $e) {
        return ['controller' => $controller, 'vars' => [], 'redirect' => $e->getMessage()];
    }

    return ['controller' => $controller, 'vars' => $controller->context->smarty->vars, 'redirect' => null];
}

function entryIds(array $group, $flag = null)
{
    $ids = [];
    foreach ($group['entries'] as $entry) {
        if ($flag === null || !empty($entry[$flag])) {
            $ids[] = $entry['id'];
        }
    }

    return $ids;
}

Db::$products = [
    ['id_product' => 45, 'name' => 'Password Guardian - Strong Password Rules', 'link_rewrite' => 'password-guardian'],
    ['id_product' => 94, 'name' => 'Product FAQ & Customer Questions', 'link_rewrite' => 'product-faq'],
    // Product 77 is deliberately absent: deleted, or no longer active.
];

MegFaqEntry::$rows = [
    ['id_megfaq' => 1, 'id_product' => 0, 'question' => 'How many shops does one licence cover?', 'answer' => "One licence covers one installation.\nAll of its shops are included."],
    ['id_megfaq' => 2, 'id_product' => 0, 'question' => 'Do you ship physical goods?', 'answer' => 'No. Everything here is a download.'],
    ['id_megfaq' => 3, 'id_product' => 0, 'question' => 'Can I get an invoice?', 'answer' => 'Yes, with every order.'],
    ['id_megfaq' => 4, 'id_product' => 45, 'question' => 'Does it work with PrestaShop 9?', 'answer' => 'Yes, from 1.7.0 through 9.'],
    ['id_megfaq' => 5, 'id_product' => 45, 'question' => 'Can I see a <demo>?', 'answer' => 'Sign in with demo & demo.'],
    ['id_megfaq' => 6, 'id_product' => 94, 'question' => 'Is the FAQ page translated?', 'answer' => 'Yes, into nine languages.'],
    ['id_megfaq' => 7, 'id_product' => 77, 'question' => 'A question about a product that is gone', 'answer' => 'Must not appear.'],
];

/* ---------------------------------------------------------------- the page */

echo 'The page' . PHP_EOL;

$result = page();
$vars = $result['vars'];
$groups = $vars['mf_groups'];

check('the page renders rather than redirecting', $result['redirect'] === null);
check('with the FAQ template', $result['controller']->template === 'module:megfaq/views/templates/front/faq.tpl');
check('no search means no query', $vars['mf_query'] === '' && $vars['mf_searching'] === false);
check('an entry whose product is gone is left out', $vars['mf_total'] === 6);
check('and without a search every entry counts as a match', $vars['mf_matches'] === 6);
check('one group per product plus the shared one', count($groups) === 3);
check('the shared group comes first', $groups[0]['id_product'] === 0);
check('under its own title', $groups[0]['title'] === 'Questions about the shop');
check('with no product link', $groups[0]['url'] === '');
check('and the shared group starts open', $groups[0]['open'] === true && $groups[0]['default_open'] === true);
check('product groups start closed', $groups[1]['open'] === false && $groups[2]['open'] === false);
check('but count as matches', $groups[1]['match'] === true && $groups[1]['matches'] === 2);
check('each product group links to its product', $groups[1]['url'] === 'https://shop.test/en/45-password-guardian.html');
check('the first entry of the first group starts open', $groups[0]['entries'][0]['open'] === true);
check('and is marked as the default', $groups[0]['entries'][0]['default_open'] === true);
check('the second one does not', $groups[0]['entries'][1]['open'] === false);
check('nor does the first entry of a product group', $groups[1]['entries'][0]['open'] === false);
check('questions are escaped once', $groups[1]['entries'][1]['question'] === 'Can I see a &lt;demo&gt;?');
check('answers too', strpos($groups[1]['entries'][1]['answer'], 'demo &amp; demo') !== false);
check('and paragraph breaks become line breaks', strpos($groups[0]['entries'][0]['answer'], '<br />') !== false);
check('the product name is passed raw for the template to escape', $groups[2]['title'] === 'Product FAQ & Customer Questions');
check('the form posts back to the page itself', $vars['mf_page_url'] === 'https://shop.test/en/faq');

$result = page([], 'https://shop.test/en/faq', ['MEGFAQ_OPEN_FIRST' => 0]);
check('with the setting off, nothing starts open inside the shared group', $result['vars']['mf_groups'][0]['entries'][0]['open'] === false);
check('though the shared group itself still does', $result['vars']['mf_groups'][0]['open'] === true);

$controller = page()['controller'];
check('the page names its canonical address', $controller->getCanonicalURL() === 'https://shop.test/en/faq');
check('and is indexable', $controller->getTemplateVarPage()['meta']['robots'] === 'index');
$crumbs = $controller->getBreadcrumbLinks();
check('the breadcrumb ends on the FAQ page', end($crumbs['links'])['url'] === 'https://shop.test/en/faq');

/* ------------------------------------------------------------------ search */

echo 'Search' . PHP_EOL;

$result = page(['q' => 'licence']);
$vars = $result['vars'];
$groups = $vars['mf_groups'];

check('the search is echoed back', $vars['mf_query'] === 'licence' && $vars['mf_searching'] === true);
check('one entry matches', $vars['mf_matches'] === 1);
check('the total is still the whole page', $vars['mf_total'] === 6);
check('the matching entry is marked', entryIds($groups[0], 'match') === [1]);
check('and opens, because there are few matches', entryIds($groups[0], 'open') === [1]);
check('the other shared entries are not', $groups[0]['entries'][1]['match'] === false && $groups[0]['entries'][1]['open'] === false);
check('its group is a match and opens', $groups[0]['match'] === true && $groups[0]['open'] === true && $groups[0]['matches'] === 1);
check('a group with no match is neither', $groups[1]['match'] === false && $groups[1]['open'] === false && $groups[1]['matches'] === 0);
check('the default flags describe the page without the search', $groups[0]['default_open'] === true && $groups[1]['default_open'] === false);
check('a search page is kept out of the index', $result['controller']->getTemplateVarPage()['meta']['robots'] === 'noindex');
check('but its canonical is the plain page', $result['controller']->getCanonicalURL() === 'https://shop.test/en/faq');

$vars = page(['q' => 'DEMO'])['vars'];
check('case does not matter', $vars['mf_matches'] === 1);
check('and the answer is searched as well as the question', entryIds($vars['mf_groups'][1], 'match') === [5]);

$vars = page(['q' => 'guardian'])['vars'];
check('a product name brings its whole group along', $vars['mf_groups'][1]['matches'] === 2 && entryIds($vars['mf_groups'][1], 'match') === [4, 5]);
check('and nothing else', $vars['mf_groups'][0]['matches'] === 0 && $vars['mf_groups'][2]['matches'] === 0);

$vars = page(['q' => "  <b>Ship</b>\t "])['vars'];
check('the search is cleaned before use', $vars['mf_query'] === 'Ship');
check('and matches through the cleaned text', $vars['mf_matches'] === 1 && entryIds($vars['mf_groups'][0], 'match') === [2]);

$vars = page(['q' => 'nothing like this'])['vars'];
check('a search with no match says so', $vars['mf_matches'] === 0);
check('and every group is hidden', !$vars['mf_groups'][0]['match'] && !$vars['mf_groups'][1]['match'] && !$vars['mf_groups'][2]['match']);

$result = page(['q' => 'a']);
check('one letter is not a search', $result['vars']['mf_searching'] === false);
check('so nothing is filtered', $result['vars']['mf_matches'] === 6);
check('and the page stays indexable', $result['controller']->getTemplateVarPage()['meta']['robots'] === 'index');
check('but the letter is still in the box', $result['vars']['mf_query'] === 'a');

/* ----------------------------------------------------------- many matches */

echo 'Many matches' . PHP_EOL;

$saved = MegFaqEntry::$rows;
$many = [];
for ($i = 1; $i <= 12; ++$i) {
    $many[] = ['id_megfaq' => $i, 'id_product' => 0, 'question' => 'Question ' . $i, 'answer' => 'The word alpha is in every answer.'];
}
$many[] = ['id_megfaq' => 13, 'id_product' => 45, 'question' => 'Another', 'answer' => 'No such word here.'];
MegFaqEntry::$rows = $many;

$vars = page(['q' => 'alpha'])['vars'];
check('twelve entries match', $vars['mf_matches'] === 12);
check('their group opens', $vars['mf_groups'][0]['open'] === true);
check('but with more than ten matches the entries stay closed', entryIds($vars['mf_groups'][0], 'open') === []);
check('while still being marked as matches', count(entryIds($vars['mf_groups'][0], 'match')) === 12);

MegFaqEntry::$rows = $saved;

/* ---------------------------------------------------------------- one group */

echo 'One group' . PHP_EOL;

MegFaqEntry::$rows = [
    ['id_megfaq' => 4, 'id_product' => 45, 'question' => 'Does it work with PrestaShop 9?', 'answer' => 'Yes.'],
    ['id_megfaq' => 5, 'id_product' => 45, 'question' => 'Can I see a demo?', 'answer' => 'Yes.'],
];

$vars = page()['vars'];
check('a single product group is the first group', count($vars['mf_groups']) === 1 && $vars['mf_groups'][0]['id_product'] === 45);
check('so it starts open', $vars['mf_groups'][0]['open'] === true);
check('with its first entry open', $vars['mf_groups'][0]['entries'][0]['open'] === true);

MegFaqEntry::$rows = $saved;

/* ---------------------------------------------------------------- redirects */

echo 'Redirects' . PHP_EOL;

$result = page([], 'https://shop.test/faq');
check('the short address is sent to the language address', $result['redirect'] === 'https://shop.test/en/faq');

$result = page(['q' => 'licence'], 'https://shop.test/faq');
check('and a search typed into it travels along', $result['redirect'] === 'https://shop.test/en/faq?q=licence');

$result = page([], 'https://shop.test/en/faq/');
check('a trailing slash is the same address', $result['redirect'] === null);

$result = page([], 'https://shop.test/en/faq', ['MEGFAQ_PAGE' => 0]);
check('a page that is switched off sends the visitor home', $result['redirect'] === 'https://shop.test/en/');

/* ------------------------------------------------------------------------- */

echo PHP_EOL;
echo $failed === 0
    ? 'OK - ' . $passed . ' assertions passed' . PHP_EOL
    : $failed . ' of ' . ($passed + $failed) . ' assertions FAILED' . PHP_EOL;

exit($failed === 0 ? 0 : 1);
