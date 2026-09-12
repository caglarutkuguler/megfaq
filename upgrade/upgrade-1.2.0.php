<?php
/**
 * @author    MEG Venture <info@megventure.com>
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.1.1 -> 1.2.0: the FAQ page gets a search box and one collapsed row per
 * product.
 *
 * Nothing in the database changes. The template is re-read from disk, but the
 * front stylesheet and script changed too, and a theme that combines its
 * assets keeps serving the old bundle until the asset cache is dropped - the
 * bundle's name is made from the file paths, which did not change. So both
 * caches go.
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_1_2_0($module)
{
    if (method_exists('Tools', 'clearSmartyCache')) {
        Tools::clearSmartyCache();
    }

    if (method_exists('Media', 'clearCache')) {
        Media::clearCache();
    }

    return true;
}
