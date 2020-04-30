<?php
/**
*   Provides automatic installation of the Astore plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 017 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

global $_DB_dbms;

require_once __DIR__ . '/functions.inc';
require_once __DIR__ . '/sql/'. $_DB_dbms. '_install.php';

// Plugin installation options
$INSTALL_plugin['astore'] = array(
    'installer' => array(
        'type'      => 'installer', 
        'version'   => '1', 
        'mode'      => 'install',
    ),
    'plugin' => array(
        'type'      => 'plugin', 
        'name'      => $_CONF_ASTORE['pi_name'],
        'ver'       => $_CONF_ASTORE['pi_version'], 
        'gl_ver'    => $_CONF_ASTORE['gl_version'],
        'url'       => $_CONF_ASTORE['pi_url'], 
        'display'   => $_CONF_ASTORE['pi_display_name'],
    ),
    array(
        'type'  => 'table', 
        'table' => $_TABLES['astore_cache'], 
        'sql'   => $_SQL['astore_cache'],
    ),
    array(
        'type'  => 'table', 
        'table' => $_TABLES['astore_catalog'], 
        'sql'   => $_SQL['astore_catalog'],
    ),
    array(
        'type'  => 'table', 
        'table' => $_TABLES['astore_categories'], 
        'sql'   => $_SQL['astore_categories'],
    ),
    array(
        'type'  => 'group', 
        'group' => $_CONF_ASTORE['pi_name'] . ' Admin', 
        'desc'  => 'Users in this group can administer the Astore plugin',
        'variable' => 'admin_group_id', 
        'admin' => true,
        'addroot' => true,
    ),
    array(
        'type' => 'feature', 
        'feature' => $_CONF_ASTORE['pi_name'] . '.admin', 
        'desc' => 'Astore Administrator',
        'variable' => 'admin_feature_id',
    ),
    array(
        'type' => 'mapping', 
        'group' => 'admin_group_id', 
        'feature' => 'admin_feature_id',
        'log' => 'Adding Admin feature to the admin group',
    ),
    array(
        'type' => 'block', 
        'name' => 'astore_random', 
        'title' => 'Random Product',
        'phpblockfn' => 'phpblock_astore_random',
        'block_type' => 'phpblock',
        'is_enabled' => 0,
        'group_id' => 'admin_group_id',
    ),
);


/**
*   Puts the datastructures for this plugin into the glFusion database
*   Note: Corresponding uninstall routine is in functions.inc
*
*   @return boolean     True if successful False otherwise
*/
function plugin_install_astore()
{
    global $INSTALL_plugin, $_CONF_ASTORE;

    $pi_name            = $_CONF_ASTORE['pi_name'];
    $pi_display_name    = $_CONF_ASTORE['pi_display_name'];
    $pi_version         = $_CONF_ASTORE['pi_version'];

    COM_errorLog("Attempting to install the $pi_display_name plugin", 1);

    $ret = INSTALLER_install($INSTALL_plugin[$pi_name]);
    if ($ret > 0) {
        return false;
    }
    return true;
}


/**
*   Loads the configuration records for the Online Config Manager
*
*   @return boolean     True = proceed with install, False = an error occured
*/
function plugin_load_configuration_astore()
{
    global $_CONF_ASTORE, $_TABLES;

    require_once __DIR__ . '/install_defaults.php';

    // Get the admin group ID that was saved previously.
    $group_id = (int)DB_getItem($_TABLES['groups'], 'grp_id', 
            "grp_name='{$_CONF_ASTORE['pi_name']} Admin'");

    return plugin_initconfig_astore($group_id);
}


/**
*   Post-installation tasks required.
*/
function plugin_postinstall_astore()
{
    global $_TABLES;

    // Add a timestamp tracker to the vars table to avoid Amazon throttling
    DB_query("INSERT INTO {$_TABLES['vars']}
            (name, value) VALUES ('astore_ts', '0')");
}

?>
