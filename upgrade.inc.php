<?php
/**
 * Upgrade routines for the Astore plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2023 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Include installation defaults to update config after upgrades. */
require_once __DIR__ . '/install_defaults.php';
use glFusion\Database\Database;
use glFusion\Log\Log;

/**
 * Perform the upgrade starting at the current version.
 *
 * @param   boolean $dvlp   True to ignore errors for development update
 * @return  integer         Error code, 0 for success
 */
function astore_do_upgrade($dvlp=false)
{
    global $_TABLES, $_CONF_ASTORE, $_PLUGIN_INFO, $_ASTORE_DEFAULT;

    $pi_name = $_CONF_ASTORE['pi_name'];

    if (isset($_PLUGIN_INFO[$_CONF_ASTORE['pi_name']])) {
        $code_ver = plugin_chkVersion_astore();
        if (is_array($_PLUGIN_INFO[$_CONF_ASTORE['pi_name']])) {
            // glFusion 1.6.6+
            $current_ver = $_PLUGIN_INFO[$_CONF_ASTORE['pi_name']]['pi_version'];
        } else {
            $current_ver = $_PLUGIN_INFO[$_CONF_ASTORE['pi_name']];
        }
        if (COM_checkVersion($current_ver, $code_ver)) {
            // Already updated to the code version, nothing to do
            return true;
        }
    } else {
        // Error determining the installed version
        return false;
    }
    $installed_ver = plugin_chkVersion_astore();
    $conf = config::get_instance();

    if (!COM_checkVersion($current_ver, '0.2.0')) {
        $current_ver = '0.2.0';
        // Check a column that's added in this update to see if the
        // title field needs to be sync'd from cache to the catalog.
        if (!_ASTOREtableHasColumn('astore_catalog', 'enabled')) {
            $update_title = true;
        } else {
            $update_title = false;
        }
        if (!astore_do_upgrade_sql($current_ver, $dvlp)) return false;
        if ($update_title) {
            $db = Database::getInstance();
            // Sync title names from cache into catalog title field.
            // Need this to have titles in admin list when cache table is removed.
            $sql1 = "SELECT cat.asin, cache.data
                FROM {$_TABLES['astore_catalog']} cat
                LEFT JOIN {$_TABLES['astore_cache']} cache
                    ON cache.asin = cat.asin";
            try {
                $stmt1 = $db->conn->executeQuery($sql1);
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
                $stmt1 = false;
            }
            if ($stmt1) {
                while ($A = $stmt1->fetchAssociative()) {
                    $data = @json_decode($A['data']);
                    if (!empty($data->ItemAttributes->Title)) {
                        $title = $data->ItemAttributes->Title;
                        $item = new Astore\Item($A['asin'], $data);
                        if (empty($item->Title())) {
                            try {
                                $db->conn->update(
                                    $_TABLES['astore_catalog'],
                                    array('title' => $title),
                                    array('asin' => $A['asin']),
                                    array(Database::STRING, Database::STRING)
                                );
                            } catch (\Throwable $e) {
                                Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
        if (!astore_do_update_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.2.1')) {
        $current_ver = '0.2.1';
        if (!astore_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!astore_do_update_version($current_ver)) return false;
    }

    if (!COM_checkVersion($current_ver, '0.3.0')) {
        $current_ver = '0.3.0';
        if (!astore_do_upgrade_sql($current_ver, $dvlp)) return false;
        if (!astore_do_update_version($current_ver)) return false;
    }

    // Update the plugin configuration
    USES_lib_install();
    require_once __DIR__ . '/install_defaults.php';
    global $astoreConfigData;
    _update_config('astore', $astoreConfigData);

    // Clear the cache
    Astore\Cache::clear();

    // Final extra check to catch code-only patch versions
    if (!COM_checkVersion($current_ver, $installed_ver)) {
        if (!astore_do_update_version($installed_ver)) return false;
    }
    return true;
}


/**
 * Update the plugin version.
 * Done at each update step to keep the version up to date
 *
 * @param   string  $version    Version to set
 * @return  boolean     True on success, False on failure
 */
function astore_do_update_version($version)
{
    global $_TABLES, $_CONF_ASTORE;

    // now update the current version number.
    try {
        Database::getInstance()->conn->update(
            $_TABLES['plugins'],
            array(
                'pi_version' => $version,
                'pi_gl_version' => $_CONF_ASTORE['gl_version'],
                'pi_homepage' => $_CONF_ASTORE['pi_url'],
            ),
            array('pi_name' => 'astore'),
            array(
                Database::STRING,
                Database::STRING,
                Database::STRING,
                Database::STRING,
            )
        );
        Log::write('system', Log::INFO, "Succesfully updated the astore Plugin version to $version!");
        return true;
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        return false;
    }
}


/**
 * Actually perform any sql updates.
 *
 * @param   string  $version    Version being upgraded TO
 * @param   boolean $dvlp       True to ignore errors and continue
 * @return  boolean     True on success, False on error
 */
function astore_do_upgrade_sql($version, $dvlp=false)
{
    global $_TABLES, $_CONF_ASTORE, $ASTORE_UPGRADE, $_DB_dbms;

    require_once __DIR__ . '/sql/mysql_install.php';

    // If no sql statements passed in, return success
    if (!isset($ASTORE_UPGRADE[$version]) || !is_array($ASTORE_UPGRADE[$version])) {
        return true;
    }

    // Execute SQL now to perform the upgrade
    Log::write('system', Log::INFO, "--Updating Astore to version $version");
    $db = Database::getInstance();
    foreach($ASTORE_UPGRADE[$version] as $sql) {
        Log::write('system', Log::INFO, "Astore Plugin $version update: Executing SQL => $sql");
        try {
            $db->conn->executeStatement($sql);
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
            if (!$dvlp) {
                return false;
            }
        }
    }
    return true;
}


/**
 * Check if a column exists in a table
 *
 * @param   string  $table      Table Key, defined in paypal.php
 * @param   string  $col_name   Column name to check
 * @return  boolean     True if the column exists, False if not
 */
function _ASTOREtableHasColumn($table, $col_name)
{
    global $_TABLES;

    try {
        $stmt = Database::getInstance()->conn->executeQuery(
            "SHOW COLUMNS FROM {$_TABLES[$table]} LIKE ?",
            array($col_name),
            array(Database::STRING)
        );
        return $stmt->rowCount() > 0 ? true : false;
    } catch (\Throwable $e) {
        Log::write('system', Log::ERROR, __FUNCTION__ . ': ' . $e->getMessage());
        return false;
    }
}
