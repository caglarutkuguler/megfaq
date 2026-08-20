<?php
/**
 * Product FAQ and Customer Questions
 *
 * The queries, against a fake database that records the SQL.
 *
 * Two rules here are easy to break and impossible to notice from the back
 * office, because breaking either one produces a page that works and is simply
 * missing rows.
 *
 * The first is the shop join. Every published query has to go through
 * megfaq_shop, and the association is written by hand because ObjectModel will
 * not do it for a module table. Drop the join and one shop sees another's FAQ;
 * drop the write and every shop sees nothing.
 *
 * The second is the language rule: an entry appears in a language only when that
 * language has both halves, and a fallback replaces both halves together. A
 * query that took the question from one language and the answer from another
 * would render perfectly and read as nonsense.
 *
 * Run: php tests/QueryTest.php
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */

define('_PS_VERSION_', '9.1.4');
define('_DB_PREFIX_', 'ps_');

abstract class ObjectModel
{
    const TYPE_INT = 1;
    const TYPE_BOOL = 2;
    const TYPE_STRING = 3;
    const TYPE_DATE = 5;
    const TYPE_HTML = 6;

    public $id;
    public $id_shop_list = [];

    /** @var bool Whether the parent insert is allowed to succeed. */
    public static $parentAddResult = true;

    /** @var int What id the parent insert leaves behind. */
    public static $nextInsertId = 1;

    public function __construct($id = null, $idLang = null, $idShop = null)
    {
    }

    public function add($autoDate = true, $nullValues = false)
    {
        $this->id = self::$nextInsertId;

        return self::$parentAddResult;
    }

    public function update($nullValues = false)
    {
        return true;
    }

    public function delete()
    {
        return true;
    }
}

class Db
{
    /** @var string[] */
    public static $queries = [];

    public static function getInstance()
    {
        return new self();
    }

    public static function reset()
    {
        self::$queries = [];
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

        return 0;
    }

    public function getRow($sql)
    {
        self::$queries[] = $sql;

        return [];
    }
}

class Validate
{
    public static function isDate($d)
    {
        return true;
    }
}

class PrestaShopLogger
{
    /** @var array */
    public static $logs = [];

    public static function addLog($message, $severity = 1, $code = null, $type = null)
    {
        self::$logs[] = $message;
    }
}

class Context
{
    public static function getContext()
    {
        $context = new stdClass();
        $context->shop = (object) ['id' => 7];

        return $context;
    }
}

function pSQL($value)
{
    return str_replace("'", "\\'", (string) $value);
}

require_once __DIR__ . '/../classes/MegFaqEntry.php';

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

function assertContains($label, $needle)
{
    check($label, strpos(Db::last(), $needle) !== false);
}

function assertNotContains($label, $needle)
{
    check($label, strpos(Db::last(), $needle) === false);
}

/* --------------------------------------------------------------- shop scope */

echo 'Shop scope' . PHP_EOL;

Db::reset();
MegFaqEntry::getForProduct(42, 7, 3);
assertContains('the product query joins megfaq_shop', 'INNER JOIN `ps_megfaq_shop`');
assertContains('and pins it to the shop it was asked about', 'fs.`id_shop` = 7');

Db::reset();
MegFaqEntry::getAll(7, 3);
assertContains('the FAQ page query joins megfaq_shop too', 'INNER JOIN `ps_megfaq_shop`');
assertContains('and pins it to the same shop', 'fs.`id_shop` = 7');

/* ------------------------------------------------------------ product scope */

echo 'Product scope' . PHP_EOL;

Db::reset();
MegFaqEntry::getForProduct(42, 7, 3, true);
assertContains('the product own entries are included', 'f.`id_product` = 42');
assertContains('and so are the shared ones', 'f.`id_product` = 0');
assertContains('with the shared ones sorted last', 'ORDER BY (f.`id_product` = 0)');

Db::reset();
MegFaqEntry::getForProduct(42, 7, 3, false);
assertContains('shared entries can be excluded', 'f.`id_product` = 42');
assertNotContains('and then the shared clause is gone', 'OR f.`id_product` = 0');

Db::reset();
$rows = MegFaqEntry::getForProduct(0, 7, 3);
check('product 0 returns nothing', $rows === []);
check('and issues no query - it would have meant "every shared entry"', Db::$queries === []);

/* ---------------------------------------------------------------- languages */

echo 'Languages' . PHP_EOL;

Db::reset();
MegFaqEntry::getForProduct(42, 7, 3, true, 0);
assertContains('without a fallback the language join is an inner one', 'INNER JOIN `ps_megfaq_lang` fl');
assertContains('on the language asked for', 'fl.`id_lang` = 3');
assertNotContains('and there is no second language join', 'fd.`id_lang`');
assertContains('an entry needs a question in that language', "fl.`question` != ''");
assertContains('and an answer in that language', "fl.`answer` != ''");

Db::reset();
MegFaqEntry::getForProduct(42, 7, 3, true, 1);
$sql = Db::last();
assertContains('with a fallback the wanted language is joined loosely', 'LEFT JOIN `ps_megfaq_lang` fl');
assertContains('and so is the fallback language', 'LEFT JOIN `ps_megfaq_lang` fd');
assertContains('the fallback join names the fallback language', 'fd.`id_lang` = 1');
check(
    'the question and the answer are taken on the same condition',
    substr_count($sql, "CASE WHEN (fl.`question` != '' AND fl.`answer` != '') THEN") === 2
);
check(
    'so a row can never mix a translated question with an untranslated answer',
    strpos($sql, 'THEN fl.`question` ELSE fd.`question` END') !== false
        && strpos($sql, 'THEN fl.`answer` ELSE fd.`answer` END') !== false
);
assertContains(
    'and a row survives if either language is complete',
    "((fl.`question` != '' AND fl.`answer` != '') OR (fd.`question` != '' AND fd.`answer` != ''))"
);
assertContains('the fallback flag survives a missing language row', 'COALESCE(NOT');

Db::reset();
MegFaqEntry::getForProduct(42, 7, 5, true, 5);
assertContains(
    'falling back to the language already being shown is not a fallback',
    'INNER JOIN `ps_megfaq_lang` fl'
);
assertNotContains('so no second join is built', 'fd.`id_lang`');

/* ------------------------------------------------------------- associations */

echo 'Shop association' . PHP_EOL;

ObjectModel::$parentAddResult = true;
ObjectModel::$nextInsertId = 31;
Db::reset();
$entry = new MegFaqEntry();
check('a saved entry reports success', $entry->add() === true);
check(
    'and its shop row is written by hand',
    (bool) array_filter(Db::$queries, function ($sql) {
        return strpos($sql, 'INSERT INTO `ps_megfaq_shop`') !== false
            && strpos($sql, '(31, 7)') !== false;
    })
);
check(
    'after clearing whatever was there',
    (bool) array_filter(Db::$queries, function ($sql) {
        return strpos($sql, 'DELETE FROM `ps_megfaq_shop`') !== false;
    })
);

ObjectModel::$nextInsertId = 0;
PrestaShopLogger::$logs = [];
Db::reset();
$broken = new MegFaqEntry();
check('an entry that saved as id 0 reports failure', $broken->add() === false);
check('and says so in the log', count(PrestaShopLogger::$logs) === 1);
check(
    'and names the cause rather than the symptom',
    strpos(PrestaShopLogger::$logs[0], 'AUTO_INCREMENT') !== false
);
check(
    'and writes no shop row pointing at nothing',
    !array_filter(Db::$queries, function ($sql) {
        return strpos($sql, 'INSERT INTO `ps_megfaq_shop`') !== false;
    })
);

ObjectModel::$nextInsertId = 31;
Db::reset();
$edit = new MegFaqEntry();
$edit->id = 31;
check('an ordinary edit succeeds', $edit->update() === true);
check(
    'and does not re-scope the entry to the current shop',
    !array_filter(Db::$queries, function ($sql) {
        return strpos($sql, 'INSERT INTO `ps_megfaq_shop`') !== false;
    })
);

Db::reset();
$rescope = new MegFaqEntry();
$rescope->id = 31;
$rescope->id_shop_list = [2, 3, 3];
check('an edit that asks for shops succeeds', $rescope->update() === true);
check(
    'and writes each of them once',
    (bool) array_filter(Db::$queries, function ($sql) {
        return strpos($sql, 'VALUES (31, 2), (31, 3)') !== false;
    })
);

Db::reset();
$gone = new MegFaqEntry();
$gone->id = 31;
$gone->delete();
assertContains('deleting an entry clears its shop rows', 'DELETE FROM `ps_megfaq_shop`');

/* ------------------------------------------------------------------ filters */

echo 'Back office filters' . PHP_EOL;

Db::reset();
MegFaqEntry::search(['status' => 'pending'], 1, 20, 0);
assertContains('pending means unpublished or unanswered', "f.`active` = 0 OR fl.`answer` IS NULL");

Db::reset();
MegFaqEntry::search(['status' => 'published'], 1, 20, 0);
assertContains('published means active and answered', "f.`active` = 1 AND fl.`answer` IS NOT NULL");

Db::reset();
MegFaqEntry::search(['scope' => 'global'], 1, 20, 0);
assertContains('the shared filter looks for product 0', 'f.`id_product` = 0');

Db::reset();
MegFaqEntry::search(['search' => "O'Brien"], 1, 20, 0);
assertContains('a quote in the search box is escaped', "O\\'Brien");

Db::reset();
MegFaqEntry::search([], 1, 20, 40);
assertContains('paging is passed through', 'LIMIT 20 OFFSET 40');

Db::reset();
MegFaqEntry::countSearch(['status' => 'pending'], 1);
$count = Db::last();
Db::reset();
MegFaqEntry::search(['status' => 'pending'], 1, 20, 0);
$list = Db::last();
check(
    'the count and the list agree about what they are looking at',
    substr($count, strpos($count, 'WHERE')) === substr($list, strpos($list, 'WHERE'), strpos($list, 'ORDER BY') - strpos($list, 'WHERE') - 1)
);

Db::reset();
MegFaqEntry::countRecentByEmail('', 60);
check('flood control on an empty address issues no query', Db::$queries === []);

Db::reset();
MegFaqEntry::countRecentByEmail("spam'er@example.com", 60);
assertContains('and escapes the address it was given', "spam\\'er@example.com");
assertContains('and counts within the window it was given', 'INTERVAL 60 MINUTE');

/* ------------------------------------------------------------------------- */

echo PHP_EOL;
echo $failed === 0
    ? 'OK - ' . $passed . ' assertions passed' . PHP_EOL
    : $failed . ' of ' . ($passed + $failed) . ' assertions FAILED' . PHP_EOL;

exit($failed === 0 ? 0 : 1);
