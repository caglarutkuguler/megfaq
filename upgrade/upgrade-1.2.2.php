<?php
/**
 * 1.2.1 -> 1.2.2: security hardening (POST configure write gate). No schema change.
 *
 * @author    MEG Venture
 * @copyright 2019-2026 MEG Venture & Consulting Ltd.
 * @license   https://opensource.org/licenses/MIT MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_2($module)
{
    return true;
}
