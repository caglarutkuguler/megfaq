<?php
/**
 * Product FAQ and Customer Questions
 *
 * Guards the multistore support added in 1.3.0.
 *
 * An entry can belong to several shops (megfaq_shop). These tests pin the pure
 * helpers, the SQL the list and the ownership check build for one shop and for
 * several, the delete rule (detach from this shop only while other shops still
 * hold the entry), the backfill upgrade, and that the back office entry points
 * read the shops of the context rather than one fixed shop.
 *
 * Run: php tests/MultistoreTest.php
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

if (PHP_SAPI === 'cli' && !defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '9.1.4'); // standalone run outside PrestaShop
}
if (!defined('_PS_VERSION_')) {
    exit;
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}

abstract class ObjectModel
{
    const TYPE_INT = 1;
    const TYPE_BOOL = 2;
    const TYPE_STRING = 3;
    const TYPE_DATE = 5;
    const TYPE_HTML = 6;

    public $id;
    public $id_shop_list = [];

    public function __construct($id = null, $idLang = null, $idShop = null)
    {
    }
}

class Db
{
    /** @var string[] */
    public static $queries = [];

    /** @var mixed */
    public static $nextValue = 0;

    public static function getInstance()
    {
        return new self();
    }

    public static function reset()
    {
        self::$queries = [];
        self::$nextValue = 0;
    }

    public static function last()
    {
        return self::$queries ? preg_replace('/\s+/', ' ', end(self::$queries)) : '';
    }

    public function executeS($sql)
    {
        self::$queries[] = $sql;

        return [];
    }

    public function execute($sql)
    {
        self::$queries[] = $sql;

        return true;
    }

    public function getValue($sql)
    {
        self::$queries[] = $sql;

        return self::$nextValue;
    }

    public function getRow($sql)
    {
        self::$queries[] = $sql;

        return [];
    }
}

class Configuration
{
    public static function get($key)
    {
        return $key === 'PS_SHOP_DEFAULT' ? 3 : false;
    }
}

function pSQL($value)
{
    return addslashes((string) $value);
}

function bqSQL($value)
{
    return str_replace('`', '\\`', (string) $value);
}

require_once __DIR__ . '/../classes/MegFaqEntry.php';

$passed = 0;
$failed = 0;

function check($condition, $label)
{
    global $passed, $failed;
    if ($condition) {
        ++$passed;
    } else {
        ++$failed;
        echo "FAIL: $label\n";
    }
}

/* ------------------------------------------------------------------------- */
/* Pure helpers                                                              */
/* ------------------------------------------------------------------------- */

check(MegFaqEntry::cleanShopIds([3, '2', 2, 0, -1, 'x', 1]) === [1, 2, 3], 'cleanShopIds: unique, positive, sorted');

check(MegFaqEntry::mergeShopList([1, 2], [1, 2], [1, 3]) === [1, 2, 3], 'mergeShopList keeps a shop the form did not offer');
check(MegFaqEntry::mergeShopList([2], [1, 2], [1, 3]) === [2, 3], 'mergeShopList detaches an unticked offered shop');
check(MegFaqEntry::mergeShopList([1, 9], [1, 2], []) === [1], 'mergeShopList ignores a posted shop that was not offered');
check(MegFaqEntry::mergeShopList([], [1, 2], [1]) === [], 'mergeShopList: nothing left is reported as empty');
check(MegFaqEntry::mergeShopList([1, 2], [1, 2], []) === [1, 2], 'a new entry takes every ticked shop');

check(MegFaqEntry::remainingShops([1, 2], [1]) === [2], 'remainingShops: another shop still holds it');
check(MegFaqEntry::remainingShops([1, 2], [1, 2]) === [], 'remainingShops: all shops leave, nothing remains');
check(MegFaqEntry::remainingShops([1], [1, 2, 3]) === [], 'remainingShops: leaving more shops than it had');
check(MegFaqEntry::remainingShops([], [1]) === [], 'remainingShops: an entry in no shop has nothing remaining');
check(MegFaqEntry::remainingShops([2, 3], [1]) === [2, 3], 'remainingShops: leaving a shop it was not in changes nothing');

$map = MegFaqEntry::groupShopRows(
    [['id_megfaq' => 4, 'id_shop' => 2], ['id_megfaq' => 4, 'id_shop' => 1], ['id_megfaq' => 5, 'id_shop' => 2]],
    [4, 5, 6]
);
check($map[4] === [1, 2] && $map[5] === [2] && $map[6] === [], 'groupShopRows: sorted lists, empty for an entry with no row');

/* ------------------------------------------------------------------------- */
/* The list and its counts                                                    */
/* ------------------------------------------------------------------------- */

Db::reset();
MegFaqEntry::search([], 1, 20, 0, 2);
$one = Db::last();
check(strpos($one, 'INNER JOIN `ps_megfaq_shop` fs ON fs.`id_megfaq` = f.`id_megfaq` AND fs.`id_shop` = 2') !== false, 'one shop id: the original join');

Db::reset();
MegFaqEntry::search([], 1, 20, 0, [2]);
check(Db::last() === $one, 'a one-shop list builds the very same SQL as a single id');

Db::reset();
MegFaqEntry::search([], 1, 20, 0, [3, 1, 2]);
$many = Db::last();
check(strpos($many, 'SELECT DISTINCT `id_megfaq` FROM `ps_megfaq_shop` WHERE `id_shop` IN (1,2,3)') !== false, 'several shops: one de-duplicated join');
check(strpos($many, 'fs.`id_shop` =') === false, 'several shops: no single-shop condition left over');

Db::reset();
MegFaqEntry::countSearch(['status' => 'published'], 1, [1, 2]);
check(strpos(Db::last(), 'IN (1,2)') !== false && strpos(Db::last(), 'COUNT(*)') !== false, 'the count uses the same shop scope as the list');

Db::reset();
MegFaqEntry::countPending(1, [1, 2]);
check(strpos(Db::last(), 'IN (1,2)') !== false, 'the pending badge uses the same shop scope');

Db::reset();
MegFaqEntry::getAll(2, 1);
check(strpos(Db::last(), 'fs.`id_shop` = 2') !== false && strpos(Db::last(), 'DISTINCT') === false, 'the storefront query is untouched: one shop, INNER JOIN megfaq_shop');

Db::reset();
MegFaqEntry::search([], 1, 20, 0, []);
check(strpos(Db::last(), 'fs.`id_shop` = 0') !== false, 'an empty list matches no entry');

/* ------------------------------------------------------------------------- */
/* Ownership                                                                  */
/* ------------------------------------------------------------------------- */

Db::reset();
Db::$nextValue = 1;
check(MegFaqEntry::belongsToShop(7, 2) === true, 'belongsToShop: one shop, found');
check(strpos(Db::last(), '`id_shop` = 2') !== false && strpos(Db::last(), 'IN (') === false, 'belongsToShop: one shop keeps the equality query');

Db::reset();
Db::$nextValue = 1;
MegFaqEntry::belongsToShop(7, [2, 1]);
check(strpos(Db::last(), '`id_shop` IN (1,2)') !== false, 'belongsToShop: a list asks for any of the shops');

Db::reset();
check(MegFaqEntry::belongsToShop(7, []) === false && Db::$queries === [], 'belongsToShop: an empty list is no, without a query');
check(MegFaqEntry::belongsToShop(0, [1]) === false, 'belongsToShop: no id is no');

/* ------------------------------------------------------------------------- */
/* Detach                                                                     */
/* ------------------------------------------------------------------------- */

$entry = new MegFaqEntry();
$entry->id = 9;
Db::reset();
check($entry->detachFromShops([2, 1]) === true, 'detachFromShops succeeds');
check(strpos(Db::last(), 'DELETE FROM `ps_megfaq_shop` WHERE `id_megfaq` = 9 AND `id_shop` IN (1,2)') === 0, 'detachFromShops deletes only this entry and those shops');
check(strpos(Db::last(), 'ps_megfaq`') === false || strpos(Db::last(), 'DELETE FROM `ps_megfaq`') === false, 'detachFromShops never deletes the entry itself');

Db::reset();
check($entry->detachFromShops([]) === false && Db::$queries === [], 'detachFromShops with no shop does nothing');

/* ------------------------------------------------------------------------- */
/* Backfill upgrade                                                           */
/* ------------------------------------------------------------------------- */

require_once __DIR__ . '/../upgrade/upgrade-1.3.0.php';

Db::reset();
check(upgrade_module_1_3_0(null) === true, 'upgrade-1.3.0 runs');
$sqls = Db::$queries;
$insert = '';
foreach ($sqls as $q) {
    if (strpos($q, 'INSERT') === 0) {
        $insert = preg_replace('/\s+/', ' ', $q);
    }
}
check(strpos($insert, 'INSERT IGNORE INTO `ps_megfaq_shop`') === 0, 'upgrade inserts into megfaq_shop, ignoring duplicates');
check(strpos($insert, 'SELECT f.`id_megfaq`, 3 FROM `ps_megfaq` f') !== false, 'upgrade uses the default shop (PS_SHOP_DEFAULT)');
check(strpos($insert, 'NOT EXISTS ( SELECT 1 FROM `ps_megfaq_shop` s WHERE s.`id_megfaq` = f.`id_megfaq`)') !== false, 'upgrade only touches entries with no association row (idempotent)');
$all = implode(' ', $sqls);
check(stripos($all, 'DELETE') === false && stripos($all, 'UPDATE') === false && stripos($all, 'ALTER') === false, 'upgrade deletes, updates and alters nothing');

/* ------------------------------------------------------------------------- */
/* Source assertions                                                          */
/* ------------------------------------------------------------------------- */

$main = file_get_contents(__DIR__ . '/../megfaq.php');
$entryPhp = file_get_contents(__DIR__ . '/../classes/MegFaqEntry.php');
$tpl = file_get_contents(__DIR__ . '/../views/templates/admin/configure.tpl');

check(substr_count($main, 'MegFaqEntry::belongsToShop($id, $this->getShopId())') === 0, 'no back office path checks a single fixed shop any more');
check(substr_count($main, 'MegFaqEntry::belongsToShop($id, $this->contextShopIds())') === 3, 'edit, toggle and the edit form use the context shops');
check(strpos($main, 'MegFaqEntry::belongsToShop($id, $shopIds)') !== false, 'delete checks the context shops');
check(strpos($main, '$idShop = $this->contextShopIds();') !== false, 'the list is read for the context shops');
check(strpos($main, '$entry->id_shop_list = $wanted;') !== false, 'saveEntry sets id_shop_list');
check(strpos($main, "Tools::getValue('mf_shops_sent')") !== false, 'the form posts its shop list explicitly');
check(strpos($main, '$wanted = $isNew ? $this->contextShopIds() : $current;') !== false, 'a new entry defaults to the shops of the context');
check(strpos($main, '$entry->detachFromShops($shopIds)') !== false, 'delete can detach instead of delete');
check(strpos($main, 'MegFaqEntry::remainingShops($held, $shopIds)') !== false, 'delete decides from the shops that would remain');
check(preg_match('/if \(Shop::isFeatureActive\(\)\) \{\s+\$held = /', $main) === 1, 'detach is gated on Shop::isFeatureActive(): one shop always deletes outright');
check(strpos($main, 'renderShopContextNotice()') !== false && strpos($main, 'alert alert-warning') !== false, 'back office shows the all-shops notice');
check(strpos($main, 'Shop::getContext() == Shop::CONTEXT_SHOP') !== false, 'the notice is limited to non-shop contexts');
check(!preg_match("/^\s*'multishop'\s*=>\s*true/m", $entryPhp), "definition does not declare 'multishop' => true");

check(strpos($tpl, 'name="mf_shops[]"') !== false && strpos($tpl, 'name="mf_shops_sent"') !== false, 'the entry form has the shop checkbox list');
check(strpos($tpl, '{if $mf_multistore}<th>{l s=\'Shops\' mod=\'megfaq\'}</th>{/if}') !== false, 'the Shops column only shows with multistore');
check(strpos($tpl, '$mf_multistore && $row.detaches') !== false, 'the delete confirmation says which will happen');
check(strpos($tpl, 'Delete this entry for good?') !== false, 'the one-shop delete text is unchanged');

preg_match("/this->version = '([0-9.]+)'/", $main, $mv);
$xml = file_get_contents(__DIR__ . '/../config.xml');
preg_match('/<version><!\[CDATA\[([0-9.]+)\]\]>/', $xml, $xv);
check(!empty($mv[1]) && version_compare($mv[1], '1.3.0', '>='), 'module version is at least 1.3.0');
check(!empty($xv[1]) && $xv[1] === $mv[1], 'config.xml agrees with the module version');

/* ------------------------------------------------------------------------- */

if ($failed) {
    echo "\n$failed FAILED, $passed passed\n";
    exit(1);
}
echo "\nOK - $passed assertions passed\n";
