<?php
/**
*   Installation Defaults used when loading the online configuration.
*   These settings are only used during the initial installation
*   and upgrade not referenced any more once the plugin is installed.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined('GVERSION')) {
    die('This file can not be used on its own!');
}

/*
*   Astore default settings
*
*   Initial Installation Defaults used when loading the online configuration
*   records. These settings are only used during the initial installation
*   and not referenced any more once the plugin is installed
*
*   @global array
*/
global $_ASTORE_DEFAULT;
$_ASTORE_DEFAULT = array(
    'aws_store_title' => '',
    'aws_access_key' => '',
    'aws_secret_key' => '',
    'aws_assoc_id' => '',
    'aws_country' => 'com',
    'aws_cache_min' => 30,
    'auto_add_catalog' => false, // automatically add requested items to catalog
    'debug_aws' => false,
    'perpage'   => 10,
    'max_feat_desc' => 600,
    'max_blk_desc' => 50,
    'sort' => 'none',       // storefront item sorting
    'notag_header' => '',   // Do not set associate tag if this header exists
    'notag_admins' => true, // Do not set associate tag for logged-in admins
);

/**
*   Initialize Astore plugin configuration
*
*   @return boolean     true: success; false: an error occurred
*/
function plugin_initconfig_astore($admin_group)
{
    global $_CONF_ASTORE, $_ASTORE_DEFAULT;

    $me = $_CONF_ASTORE['pi_name'];
    $c = config::get_instance();
    if (!$c->group_exists($me)) {

        $c->add('sg_main', NULL, 'subgroup', 0, 0, NULL, 0, true, $me);

        $c->add('fs_main', NULL, 'fieldset', 0, 0, NULL, 0, true, $me);

        $c->add('store_title', $_ASTORE_DEFAULT['store_title'],
                'text', 0, 0, 0, 10, true, $me);
        $c->add('aws_access_key', $_ASTORE_DEFAULT['aws_access_key'],
                'text', 0, 0, 0, 20, true, $me);
        $c->add('aws_secret_key', $_ASTORE_DEFAULT['aws_secret_key'],
                'passwd', 0, 0, 3, 30, true, $me);
        $c->add('aws_assoc_id', $_ASTORE_DEFAULT['aws_assoc_id'],
                'text', 0, 0, 0, 40, true, $me);
        $c->add('aws_country', $_ASTORE_DEFAULT['aws_country'],
                'select', 0, 0, 1, 50, true, $me);
        $c->add('aws_cache_min', $_ASTORE_DEFAULT['aws_cache_min'],
                'text', 0, 0, 0, 60, true, $me);
        $c->add('debug_aws', $_ASTORE_DEFAULT['debug_aws'],
                'select', 0, 0, 2, 70, true, $me);
        $c->add('auto_add_catalog', $_ASTORE_DEFAULT['auto_add_catalog'],
                'select', 0, 0, 2, 80, true, $me);
        $c->add('perpage', $_ASTORE_DEFAULT['perpage'],
                'text', 0, 0, 0, 90, true, $me);
        $c->add('max_feat_desc', $_ASTORE_DEFAULT['max_feat_desc'],
                'text', 0, 0, 0, 100, true, $me);
        $c->add('max_blk_desc', $_ASTORE_DEFAULT['max_blk_desc'],
                'text', 0, 0, 0, 110, true, $me);
        $c->add('sort', $_ASTORE_DEFAULT['sort'],
                'select', 0, 0, 3, 120, true, $me);
        $c->add('notag_header', $_ASTORE_DEFAULT['notag_header'],
                'text', 0, 0, 0, 130, true, $me);
        $c->add('notag_admins', $_ASTORE_DEFAULT['notag_admins'],
                'select', 0, 0, 2, 140, true, $me);
        return true;
    } else {
        return false;
    }
}

?>
