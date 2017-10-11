<?php
/**
*   Default English Language file for the Astore plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
* The plugin's lang array
* @global array $LANG_ASTORE
*/
$LANG_ASTORE = array(
'admin_title' => 'Amazon Store Administration',
'shop_now'  => 'Shop Now',
'title'     => 'Description',
'items'     => 'Items',
'version'   => 'Version',
);

// Localization of the Admin Configuration UI
$LANG_configsections['astore'] = array(
    'label' => 'Astore',
    'title' => 'Astore Configuration',
);

$LANG_confignames['astore'] = array(
    'aws_access_key' => 'AWS Access Key',
    'aws_secret_key' => 'AWS Secret key',
    'aws_assoc_id' => 'Associate Tag',
    'aws_country' => 'AWS Country (Domain)',
    'aws_cache_min' => 'Minutes to cache product info',
    'aws_debug' => 'Debug AWS requests?',
    'perpage' => 'Items to show per page',
    'feat_to_catalog' => 'Add featured items to catalog?',
    'store_title' => 'Store Title',
    'max_feat_desc' => 'Max Featured Item Description (chars)',
    'max_blk_desc' => 'Max Item Description in blocks (chars)',
);

$LANG_configsubgroups['astore'] = array(
    'sg_main' => 'Main Settings',
);

$LANG_fs['astore'] = array(
    'fs_main' => 'Main Astore Settings',
);

// Note: entries 0, 1, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['astore'] = array(
    0 => array('True' => 1, 'False' => 0),
    1 => array('com' => 'com', 'co.uk' => 'co.uk', 'ca' => 'ca', 'fr' => 'fr',
            'co.jp' => 'co.jp', 'it' => 'it', 'cn' => 'cn', 'es' => 'es', 'de' => 'de'),
    2 => array('Yes' => 1, 'No' => 0),
);

?>
