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
 * 1.2.0 -> 1.2.1: security hardening only (ads widget fetch pinned to its own
 * host and its feed URLs scheme-checked, shop ownership enforced on the admin
 * entry actions, product ownership enforced on the ask endpoint, merchant
 * notification mail bounded per hour).
 *
 * Nothing in the database changes.
 *
 * @param MegFaq $module
 *
 * @return bool
 */
function upgrade_module_1_2_1($module)
{
    return true;
}
