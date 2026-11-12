<?php
/**
 * 1.2.3 -> 1.3.0: multistore support.
 *
 * No schema change. One repair: every published query joins megfaq_shop, so an
 * entry with no row there is invisible on the storefront and, from 1.3.0, in
 * the back office list too. This gives each such entry a row for the default
 * shop. Entries that already have rows are not touched.
 *
 * Safe to run twice: the second run finds no entry without a row. Nothing is
 * deleted and no row is changed.
 *
 * @author    MEG Venture
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_0($module)
{
    $db = Db::getInstance();
    $table = _DB_PREFIX_ . 'megfaq';
    $shopTable = _DB_PREFIX_ . 'megfaq_shop';

    // The association table is created at install; a restored partial backup
    // can lack it, and the backfill below needs it.
    $engine = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';
    if (!$db->execute(
        'CREATE TABLE IF NOT EXISTS `' . bqSQL($shopTable) . '` ('
        . ' `id_megfaq` int(10) unsigned NOT NULL,'
        . ' `id_shop` int(10) unsigned NOT NULL,'
        . ' PRIMARY KEY (`id_megfaq`, `id_shop`)'
        . ') ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4;'
    )) {
        return false;
    }

    $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
    if ($idShop < 1) {
        $idShop = 1;
    }

    return (bool) $db->execute(
        'INSERT IGNORE INTO `' . bqSQL($shopTable) . '` (`id_megfaq`, `id_shop`)'
        . ' SELECT f.`id_megfaq`, ' . $idShop
        . ' FROM `' . bqSQL($table) . '` f'
        . ' WHERE f.`id_megfaq` > 0 AND NOT EXISTS ('
        . ' SELECT 1 FROM `' . bqSQL($shopTable) . '` s WHERE s.`id_megfaq` = f.`id_megfaq`)'
    );
}
