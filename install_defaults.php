<?php
/**
 * Installation Defaults used when loading the online configuration.
 * These settings are only used during the initial installation
 * and upgrade not referenced any more once the plugin is installed.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2021 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Do nothing if GVERSION is not defined */
if (!defined('GVERSION')) {
    die('This file can not be used on its own!');
}

/*
 * Astore default settings.
 *
 * Initial Installation Defaults used when loading the online configuration
 * records. These settings are only used during the initial installation
 * and not referenced any more once the plugin is installed
 *
 * @global array
 */
global $astoreConfigData;
$astoreConfigData = array(
    array(
        'name' => 'sg_main',
        'default_value' => NULL,
        'type' => 'subgroup',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'fs_main',
        'default_value' => NULL,
        'type' => 'fieldset',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => NULL,
        'sort' => 0,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'store_title',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 10,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'is_open',
        'default_value' => '1',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 20,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'use_api',
        'default_value' => '0',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 30,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'aws_access_key',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 40,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'aws_secret_key',
        'default_value' => '',
        'type' => 'passwd',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 50,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'aws_assoc_id',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 60,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'aws_region',
        'default_value' => 'us',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 1,
        'sort' => 70,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'aws_cache_min',
        'default_value' => '180',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 80,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'debug_aws',
        'default_value' => false,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 90,
        'set' => true,
        'group' => 'astore',
    ),
    // automatically add requested items to catalog
    array(
        'name' => 'auto_add_catalog',
        'default_value' => false,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 100,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'perpage',
        'default_value' => '10',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 110,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'max_feat_desc',
        'default_value' => '600',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 120,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'max_blk_desc',
        'default_value' => '50',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 130,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'sort',
        'default_value' => 'none',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 3,
        'sort' => 140,
        'set' => true,
        'group' => 'astore',
    ),
    // Do not set associate tag if this header exists
    array(
        'name' => 'notag_header',
        'default_value' => '',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 150,
        'set' => true,
        'group' => 'astore',
    ),
    // Do not set associate tag for logged-in admins
    array(
        'name' => 'notag_admins',
        'default_value' => true,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 160,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'cb_enable',
        'default_value' => false,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 2,
        'sort' => 170,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'grp_search',
        'default_value' => 1,       // only Root can search by default
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,     // helper function
        'sort' => 180,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'def_catid',
        'default_value' => 1,
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,     // helper function
        'sort' => 190,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'link_to',
        'default_value' => 'amazon',
        'type' => 'select',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 4,
        'sort' => 200,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'disclaimer',
        'default_value' => 'Paid Affiliate Link',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 210,
        'set' => true,
        'group' => 'astore',
    ),
    array(
        'name' => 'full_disclaimer',
        'default_value' => 'This site may earn commissions for sales made from these links',
        'type' => 'text',
        'subgroup' => 0,
        'fieldset' => 0,
        'selection_array' => 0,
        'sort' => 220,
        'set' => true,
        'group' => 'astore',
    ),
);

/**
 * Initialize Astore plugin configuration.
 *
 * @param   integer $admin_group    Administrative group ID
 * @return  boolean     true: success; false: an error occurred
 */
function plugin_initconfig_astore($admin_group)
{
    global $astoreConfigData;

    $c = config::get_instance();
    if (!$c->group_exists('astore')) {
        USES_lib_install();
        foreach ($astoreConfigData AS $cfgItem) {
            _addConfigItem($cfgItem);
        }
    }
    return true;
}
