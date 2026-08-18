<?php
/**
 * Product FAQ and Customer Questions
 *
 * One question, its answer, and where it belongs.
 *
 * Two things about this model are worth explaining before anyone reads the SQL.
 *
 * First, id_product = 0 means "every product". A shop selling forty modules
 * answers "how many shops does the licence cover?" once, not forty times, and a
 * FAQ module that cannot do that is a FAQ module nobody finishes filling in. The
 * product page therefore always unions two sets, and every query that means
 * "this product only" has to say `id_product = X` rather than trust a join.
 *
 * Second, question and answer live in a -_lang table. A FAQ is prose; it cannot
 * be language-neutral the way a rating can. Keeping one row per entry with a
 * translation per language means a shop can publish the English answer today and
 * add the Polish one next month without the entry changing identity, and means
 * an untranslated entry simply does not appear in that language rather than
 * appearing in the wrong one.
 *
 * A visitor's question arrives as a row with a question, no answer and
 * active = 0. It becomes a FAQ entry when someone writes the answer. That is why
 * there is no separate "questions" table: the moderation queue and the published
 * FAQ are the same list at two stages of the same life.
 *
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture
 * @license   Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MegFaqEntry extends ObjectModel
{
    /** @var int */
    public $id_megfaq;

    /** @var int 0 means the entry belongs to every product. */
    public $id_product = 0;

    /** @var int Set when a signed-in customer asked; 0 for a guest or the shop. */
    public $id_customer = 0;

    /** @var string Who asked. Empty when the shop wrote the entry itself. */
    public $customer_name = '';

    /** @var string Only ever used to reply. Never rendered on the front office. */
    public $customer_email = '';

    /** @var int Lower sorts first inside its group. */
    public $position = 0;

    /** @var bool */
    public $active = false;

    /** @var string|array */
    public $question;

    /** @var string|array */
    public $answer;

    /** @var string */
    public $date_add;

    /** @var string */
    public $date_upd;

    /**
     * There is deliberately no 'multishop' => true here, even though megfaq_shop
     * exists and every front office query joins against it.
     *
     * ObjectModel only writes the _shop rows when Shop::isTableAssociated() says
     * the table is shop-associated, and that answer comes from Shop::$asso_tables
     * - a hardcoded list of CORE tables. A module table is not in it. Declaring
     * multishop here would look right, do nothing, and leave every entry without
     * a _shop row - which the INNER JOIN in published() would then filter out, so
     * the FAQ would stay empty forever with no error anywhere to explain it. The
     * association is maintained by hand in add(), update() and delete() below.
     *
     * 'multilang' is a different matter and is left on: ObjectModel writes the
     * _lang rows itself, without consulting any registry.
     */
    public static $definition = [
        'table' => 'megfaq',
        'primary' => 'id_megfaq',
        'multilang' => true,
        'fields' => [
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'customer_name' => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'size' => 60],
            'customer_email' => ['type' => self::TYPE_STRING, 'validate' => 'isEmail', 'size' => 128],
            'position' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedInt'],
            'active' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'date_add' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'date_upd' => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
            'question' => [
                'type' => self::TYPE_STRING,
                'lang' => true,
                'validate' => 'isCleanHtml',
                'size' => 500,
            ],
            'answer' => [
                'type' => self::TYPE_HTML,
                'lang' => true,
                'validate' => 'isCleanHtml',
            ],
        ],
    ];

    /**
     * @param bool $autoDate
     * @param bool $nullValues
     *
     * @return bool
     */
    public function add($autoDate = true, $nullValues = false)
    {
        if (empty($this->date_add) || !Validate::isDate($this->date_add)) {
            $this->date_add = date('Y-m-d H:i:s');
        }
        $this->date_upd = date('Y-m-d H:i:s');

        if (!parent::add($autoDate, $nullValues)) {
            return false;
        }

        // The same guard megtestimonial 2.1.1 had to learn the hard way: a table
        // whose AUTO_INCREMENT has been lost accepts the INSERT, stores id 0 and
        // reports success. Every row after that collides on the primary key and
        // the front office joins return nothing, which looks like a display bug
        // and is not one.
        if (!(int) $this->id) {
            PrestaShopLogger::addLog(
                'megfaq: the entry saved with id 0. The AUTO_INCREMENT on '
                . _DB_PREFIX_ . 'megfaq.id_megfaq is missing - restore it before accepting questions.',
                3, null, 'MegFaqEntry'
            );

            return false;
        }

        return $this->saveShopAssociation();
    }

    /**
     * @param bool $nullValues
     *
     * @return bool
     */
    public function update($nullValues = false)
    {
        $this->date_upd = date('Y-m-d H:i:s');

        if (!parent::update($nullValues)) {
            return false;
        }

        // Only rewrite the association when the caller actually asked for one.
        // An ordinary back office edit must not silently re-scope an entry to
        // whichever shop the employee happens to be looking at.
        return $this->id_shop_list ? $this->saveShopAssociation() : true;
    }

    /**
     * @return bool
     */
    public function delete()
    {
        $this->clearShopAssociation();

        return parent::delete();
    }

    /**
     * Maintain megfaq_shop by hand - see the note on $definition for why
     * ObjectModel cannot do it for a module table.
     *
     * @return bool
     */
    private function saveShopAssociation()
    {
        // Belt and braces against the id-0 case above: a row here pointing at no
        // entry is invisible in the back office and impossible to clear from the
        // interface, so it must never be written in the first place.
        if (!(int) $this->id) {
            return false;
        }

        $shops = $this->id_shop_list;
        if (!$shops) {
            $shops = [(int) Context::getContext()->shop->id];
        }

        $this->clearShopAssociation();

        $values = [];
        foreach (array_unique(array_map('intval', $shops)) as $idShop) {
            $values[] = '(' . (int) $this->id . ', ' . (int) $idShop . ')';
        }

        if (!$values) {
            return true;
        }

        return (bool) Db::getInstance()->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'megfaq_shop` (`id_megfaq`, `id_shop`)'
            . ' VALUES ' . implode(', ', $values)
        );
    }

    /**
     * @return void
     */
    private function clearShopAssociation()
    {
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'megfaq_shop`'
            . ' WHERE `id_megfaq` = ' . (int) $this->id
        );
    }

    /* --------------------------------------------------------------- front */

    /**
     * Published entries for a product page: the product's own, then the ones
     * that belong to every product.
     *
     * Product-specific first on purpose. Someone reading a product page has a
     * question about that product; the licence and compatibility answers are
     * useful but they are not what brought them here.
     *
     * @param int  $idProduct
     * @param int  $idShop
     * @param int  $idLang
     * @param bool $includeGlobal
     *
     * @return array
     */
    public static function getForProduct($idProduct, $idShop, $idLang, $includeGlobal = true, $fallbackLang = 0)
    {
        $idProduct = (int) $idProduct;

        if (!$idProduct) {
            return [];
        }

        $scope = $includeGlobal
            ? '(f.`id_product` = ' . $idProduct . ' OR f.`id_product` = 0)'
            : 'f.`id_product` = ' . $idProduct;

        return self::published(
            $scope,
            $idShop,
            $idLang,
            // 0 = the product's own, 1 = the shared ones.
            'ORDER BY (f.`id_product` = 0), f.`position` ASC, f.`id_megfaq` ASC',
            $fallbackLang
        );
    }

    /**
     * Everything published, for the shop's own FAQ page.
     *
     * @param int $idShop
     * @param int $idLang
     *
     * @return array
     */
    public static function getAll($idShop, $idLang, $fallbackLang = 0)
    {
        return self::published(
            '1',
            $idShop,
            $idLang,
            'ORDER BY (f.`id_product` != 0), f.`id_product` ASC, f.`position` ASC, f.`id_megfaq` ASC',
            $fallbackLang
        );
    }

    /**
     * The shared clause behind both.
     *
     * An entry is shown in a language when that language has BOTH a question and
     * an answer. Half a translation is not a translation: a question in Polish
     * under an answer in English reads as a mistake, and the shopper cannot tell
     * whether the answer is even about their question.
     *
     * When $fallbackLang is set, an entry with no complete translation falls back
     * to that language whole - both halves from the same place - instead of
     * disappearing. That is the difference between a shop that translates over
     * several months having a useful FAQ page throughout and having an empty one
     * in seven languages until the last day.
     *
     * @param string $scope
     * @param int    $idShop
     * @param int    $idLang
     * @param string $order
     * @param int    $fallbackLang 0 to hide untranslated entries instead
     *
     * @return array
     */
    private static function published($scope, $idShop, $idLang, $order, $fallbackLang = 0)
    {
        $idLang = (int) $idLang;
        $fallbackLang = (int) $fallbackLang;

        // Falling back to the language we are already showing is not a fallback.
        if ($fallbackLang === $idLang) {
            $fallbackLang = 0;
        }

        $wanted = "(fl.`question` != '' AND fl.`answer` != '')";
        $spare = "(fd.`question` != '' AND fd.`answer` != '')";

        $select = $fallbackLang
            ? ' CASE WHEN ' . $wanted . ' THEN fl.`question` ELSE fd.`question` END AS `question`,'
                . ' CASE WHEN ' . $wanted . ' THEN fl.`answer` ELSE fd.`answer` END AS `answer`,'
                // COALESCE because a language with no row at all makes the
                // comparison NULL rather than false, and a flag that reads
                // NULL when the answer is most certainly a fallback is worse
                // than no flag.
                . ' COALESCE(NOT ' . $wanted . ', 1) AS `is_fallback`'
            : ' fl.`question`, fl.`answer`, 0 AS `is_fallback`';

        $join = $fallbackLang
            ? ' LEFT JOIN `' . _DB_PREFIX_ . 'megfaq_lang` fl'
                . ' ON fl.`id_megfaq` = f.`id_megfaq` AND fl.`id_lang` = ' . $idLang
                . ' LEFT JOIN `' . _DB_PREFIX_ . 'megfaq_lang` fd'
                . ' ON fd.`id_megfaq` = f.`id_megfaq` AND fd.`id_lang` = ' . $fallbackLang
            : ' INNER JOIN `' . _DB_PREFIX_ . 'megfaq_lang` fl'
                . ' ON fl.`id_megfaq` = f.`id_megfaq` AND fl.`id_lang` = ' . $idLang;

        $have = $fallbackLang
            ? '(' . $wanted . ' OR ' . $spare . ')'
            : $wanted;

        $rows = Db::getInstance()->executeS(
            'SELECT f.`id_megfaq`, f.`id_product`, f.`position`,'
            . $select
            . ' FROM `' . _DB_PREFIX_ . 'megfaq` f'
            . self::shopJoin($idShop)
            . $join
            . ' WHERE f.`active` = 1'
            . ' AND ' . $scope
            . ' AND ' . $have
            . ' ' . $order
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param int $idShop
     *
     * @return string
     */
    private static function shopJoin($idShop)
    {
        return ' INNER JOIN `' . _DB_PREFIX_ . 'megfaq_shop` fs'
            . ' ON fs.`id_megfaq` = f.`id_megfaq` AND fs.`id_shop` = ' . (int) $idShop;
    }

    /* --------------------------------------------------------------- admin */

    /**
     * How many questions are waiting: not published, or published with nothing
     * written in the shop's own language yet.
     *
     * @param int $idLang
     *
     * @return int
     */
    public static function countPending($idLang)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*)'
            . ' FROM `' . _DB_PREFIX_ . 'megfaq` f'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'megfaq_lang` fl'
            . ' ON fl.`id_megfaq` = f.`id_megfaq` AND fl.`id_lang` = ' . (int) $idLang
            . " WHERE f.`active` = 0 OR fl.`answer` IS NULL OR fl.`answer` = ''"
        );
    }

    /**
     * The back office list.
     *
     * @param array $filters status|scope|search
     * @param int   $idLang
     * @param int   $limit
     * @param int   $offset
     *
     * @return array
     */
    public static function search(array $filters, $idLang, $limit, $offset)
    {
        $rows = Db::getInstance()->executeS(
            'SELECT f.*, fl.`question`, fl.`answer`'
            . ' FROM `' . _DB_PREFIX_ . 'megfaq` f'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'megfaq_lang` fl'
            . ' ON fl.`id_megfaq` = f.`id_megfaq` AND fl.`id_lang` = ' . (int) $idLang
            . ' WHERE ' . self::filterClause($filters)
            . ' ORDER BY f.`date_add` DESC'
            . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array $filters
     * @param int   $idLang
     *
     * @return int
     */
    public static function countSearch(array $filters, $idLang)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*)'
            . ' FROM `' . _DB_PREFIX_ . 'megfaq` f'
            . ' LEFT JOIN `' . _DB_PREFIX_ . 'megfaq_lang` fl'
            . ' ON fl.`id_megfaq` = f.`id_megfaq` AND fl.`id_lang` = ' . (int) $idLang
            . ' WHERE ' . self::filterClause($filters)
        );
    }

    /**
     * Built once so the list and its count can never disagree about what they
     * are looking at - a paginator that counts a different set than it shows is
     * a bug that only appears on page two.
     *
     * @param array $filters
     *
     * @return string
     */
    private static function filterClause(array $filters)
    {
        $where = ['1'];

        $status = isset($filters['status']) ? (string) $filters['status'] : '';
        if ($status === 'pending') {
            $where[] = "(f.`active` = 0 OR fl.`answer` IS NULL OR fl.`answer` = '')";
        } elseif ($status === 'published') {
            $where[] = "f.`active` = 1 AND fl.`answer` IS NOT NULL AND fl.`answer` != ''";
        }

        $scope = isset($filters['scope']) ? (string) $filters['scope'] : '';
        if ($scope === 'global') {
            $where[] = 'f.`id_product` = 0';
        } elseif ($scope === 'product') {
            $where[] = 'f.`id_product` != 0';
        }

        if (!empty($filters['id_product'])) {
            $where[] = 'f.`id_product` = ' . (int) $filters['id_product'];
        }

        if (!empty($filters['search'])) {
            $needle = pSQL((string) $filters['search']);
            $where[] = "(fl.`question` LIKE '%" . $needle . "%'"
                . " OR fl.`answer` LIKE '%" . $needle . "%'"
                . " OR f.`customer_name` LIKE '%" . $needle . "%')";
        }

        return implode(' AND ', $where);
    }

    /**
     * Product ids that have at least one entry, so the back office and the FAQ
     * page can name them without one query per row.
     *
     * @return int[]
     */
    public static function productIdsInUse()
    {
        $rows = Db::getInstance()->executeS(
            'SELECT DISTINCT `id_product` FROM `' . _DB_PREFIX_ . 'megfaq`'
            . ' WHERE `id_product` != 0'
        );

        $ids = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $ids[] = (int) $row['id_product'];
        }

        return $ids;
    }

    /**
     * The next free position inside a group, so a new entry lands at the end
     * rather than fighting an existing one for position 0.
     *
     * @param int $idProduct
     *
     * @return int
     */
    public static function nextPosition($idProduct)
    {
        return 1 + (int) Db::getInstance()->getValue(
            'SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . 'megfaq`'
            . ' WHERE `id_product` = ' . (int) $idProduct
        );
    }

    /**
     * How many questions this address has asked since a given time.
     *
     * Flood control counts by e-mail rather than by IP: shoppers behind one
     * office NAT share an IP and would lock each other out, and a determined
     * spammer changes IP more easily than they change the address they want the
     * answer sent to.
     *
     * @param string $email
     * @param int    $minutes
     *
     * @return int
     */
    public static function countRecentByEmail($email, $minutes)
    {
        $email = trim((string) $email);

        if ($email === '') {
            return 0;
        }

        return (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'megfaq`'
            . " WHERE `customer_email` = '" . pSQL($email) . "'"
            . ' AND `date_add` > DATE_SUB(NOW(), INTERVAL ' . (int) $minutes . ' MINUTE)'
        );
    }
}
