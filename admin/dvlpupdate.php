<?php
/**
 * Apply updates to the Astore plugin during development.
 * Calls upgrade function with "ignore_errors" set so repeated SQL statements
 * won't cause functions to abort.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2023 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.3.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

require_once '../../../lib-common.php';
if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to access the Astore Development Code Upgrade Routine without proper permissions.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: " . $_SERVER['REMOTE_ADDR'],1);
    COM_404();
    exit;
}
require_once ASTORE_PI_PATH . '/upgrade.inc.php';   // needed for set_version()
if (function_exists('CACHE_clear')) {
    CACHE_clear();
}
\Astore\Cache::clear();

// Force the plugin version to the previous version and do the upgrade
// Start with version 2.0.0, last version to reference the astore_cache table.
$_PLUGIN_INFO['astore']['pi_version'] = '0.2.0';
astore_do_upgrade(true);

// need to clear the template cache so do it here
if (function_exists('CACHE_clear')) {
    CACHE_clear();
}
header('Location: '.$_CONF['site_admin_url'].'/plugins.php?msg=600');
exit;
