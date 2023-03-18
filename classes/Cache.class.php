<?php
/**
 * Class to handle Cache operations.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018-2023 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.3
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


    /**
     * Get item information from the cache, if present.
     *
     * @param   string  $asin   Item number
     * @return  mixed       Item object, NULL if not present
     */
    public static function get(string $asin) : ?object
    {
        return \glFusion\Cache\Cache::getInstance()
            ->get(self::_makeKey($asin));
    }


    /**
     * Sets an item's data into the cach.
     *
     * @param   string  $asin   Item number
     * @param   mixed   $data   Data to cache
     * @return  boolean     True on success, False on error
     */
    public static function set(string $asin, $data) : bool
    {
        global $_CONF_ASTORE, $_CONF;

        $cache_secs = (int)$_CONF_ASTORE['aws_cache_min'] * 60;
        if ($cache_secs == 0) {     // caching disabled.
            return true;
        }
        $cache_secs = rand($cache_secs * .75, $cache_secs * 1.25);

        // Make sure the current timestamp gets cached
        if (!isset($data->_timestamp)) {
            $data->_timestamp = $_CONF['_now']->toMySQL(true);
        }
        return \glFusion\Cache\Cache::getInstance()
            ->set(self::_makeKey($asin), $data, self::TAG, $cache_secs);
    }


    /**
     * Create a unique cache key.
     *
     * @param   string  $key    Original key, usually an ASIN
     * @return  string          Encoded key string to use as a cache ID
     */
    private static function _makeKey(string $key) : string
    {
        return self::TAG . '_' . md5($key);
    }


    /**
     * Get the timestamp of the last Amazon query.
     *
     * @return  integer     Timestamp value
     */
    public static function getTimestamp() : int
    {
        $ts = (int)\glFusion\Cache\Cache::getInstance()->get('astore_ts');
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
    public static function setTimestamp() : bool
    {
        return \glFusion\Cache\Cache::getInstance()->set('astore_ts', time(), self::TAG);
    }


    /**
     * Clear the cache, forcing future requests to be refreshed from Amazon.
     *
     * @return  boolean     True on success, False on error
     */
    public static function clear() : bool
    {
        return \glFusion\Cache\Cache::getInstance()->deleteItemsByTag(self::TAG);
    }


    /**
     * Delete a single item from cache.
     *
     * @param   string  $asin   ASIN of item to delete
     */
    public static function delete(string $asin) : void
    {
        \glFusion\Cache\Cache::getInstance()
            ->delete(self::_makeKey($asin));
    }

}
