<?php
/**
 * Class to handle Cache operations.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2020 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.0
 * @since       v0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore;


/**
 * Class for caching items.
 * Uses the cache setting for glFusion >= 2.0.0, or a local DB table
 * for earlier versions.
 * @package astore
 */
class Cache
{
    /** Tag applied to all cache items and prepended to cache IDs.
     * @const string */
    const TAG = 'astore';

    /** Minimum glFusion version that supports caching.
     * @const string */
    const MIN_GVERSION = '2.0.0';

    /**
     * Get item information from the cache, if present.
     *
     * @param   string  $asin   Item number
     * @return  mixed       Item object, NULL if not present
     */
    public static function get($asin)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES;

            $asin = DB_escapeString($asin);
            $data = DB_getItem($_TABLES['astore_cache'], 'data',
                "asin = '$asin' AND exp > UNIX_TIMESTAMP()");
            if (!empty($data)) {
                return json_decode($data);
            } else {
                return NULL;
            }
        } else {
            return \glFusion\Cache\Cache::getInstance()
                ->get(self::_makeKey($asin));
        }
    }


    /**
     * Sets an item's data into the cach.
     *
     * @param   string  $asin   Item number
     * @param   string  $data   JSON data object
     * @return  boolean     True on success, False on error
     */
    public static function set($asin, $data)
    {
        global $_CONF_ASTORE;

        $cache_secs = (int)$_CONF_ASTORE['aws_cache_min'] * 60;
        if ($cache_secs < 600) $cache_secs = 1800;
        $cache_secs = rand($cache_secs * .75, $cache_secs * 1.25);
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES;

            $asin = DB_escapeString($asin);
            $data = DB_escapeString(json_encode($data));
            $type = 0;
            $sql = "INSERT INTO {$_TABLES['astore_cache']} SET
                    asin = '$asin',
                    data = '$data',
                    exp = UNIX_TIMESTAMP() + $cache_secs
                ON DUPLICATE KEY UPDATE
                    data = '$data',
                    exp = UNIX_TIMESTAMP() + $cache_secs";
            //echo $sql;die;
            DB_query($sql);
        } else {
            return \glFusion\Cache\Cache::getInstance()
                ->set(self::_makeKey($asin), $data, self::TAG, $cache_secs);
        }
    }


    /**
     * Create a unique cache key.
     *
     * @param   string  $key    Original key, usually an ASIN
     * @return  string          Encoded key string to use as a cache ID
     */
    private static function _makeKey($key)
    {
        return self::TAG . '_' . md5($key);
    }


    /**
     * Get the timestamp of the last Amazon query.
     *
     * @return  integer     Timestamp value
     */
    public static function getTimestamp()
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_VARS;
            $ts = $_VARS['astore_ts'];
        } else {
            $ts = (int)\glFusion\Cache\Cache::getInstance()->get('astore_ts');
        }
        if (!$ts) {
            $ts = time();
            self::setTimestamp();
        }
        return $ts;
    }


    /**
     * Update the timestamp in cache with the current time.
     * Expiration is not set.
     *
     * @return  boolean     True on success, False on error
     */
    public static function setTimestamp()
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES, $_VARS;

            $_VARS['astore_ts'] = time();
            DB_query("UPDATE {$_TABLES['vars']}
                SET value = '{$_VARS['astore_ts']}'
                WHERE name = 'astore_ts'");
        } else {
            return \glFusion\Cache\Cache::getInstance()->set('astore_ts', time(), self::TAG);
        }
    }


    /**
     * Clear the cache, forcing future requests to be refreshed from Amazon.
     *
     * @return  boolean     True on success, False on error
     */
    public static function clearCache()
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES;
            DB_query("TRUNCATE {$_TABLES['astore_cache']}");
        } else {
            return \glFusion\Cache\Cache::getInstance()->deleteItemsByTag(self::TAG);
        }
    }


    public static function delete($asin)
    {
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            global $_TABLES;
            DB_delete($_TABLES['astore_cache'], 'asin', $asin);
        } else {
            \glFusion\Cache\Cache::getInstance()
                ->delete(self::_makeKey($asin));
        }
    }

}

?>
