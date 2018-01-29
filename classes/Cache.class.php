<?php
/**
*   Class to handle Cache operations
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2018 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.1.2
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Astore;

/**
*   Class for Amazon Items
*   @package astore
*/
class Cache
{
    private static $tag = 'astore';

    /**
    *   Get item information from the cache, if present
    *
    *   @param  string  $asin   Item number
    *   @return mixed       Item object, NULL if not present
    */
    public static function getCache($asin)
    {
        if (GVERSION < '1.8.0') {
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
            return \glFusion\Cache::getInstance()
                ->get(self::_makeKey($asin));
        }
    }


    /**
    *   Sets an item's data into the cache
    *
    *   @param  string  $asin   Item number
    *   @param  string  $data   JSON data object
    */
    public static function setCache($asin, $data)
    {
        global $_CONF_ASTORE;

        $cache_secs = (int)$_CONF_ASTORE['aws_cache_min'] * 60;
        if ($cache_secs < 600) $cache_secs = 1800;
        if (GVERSION < '1.8.0') {
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
            \glFusion\Cache::getInstance()
            ->set(self::_makeKey($asin), $data, self::$tag, $cache_secs);
        }
    }


    /**
    *   Create a unique cache key.
    *
    *   @param  string  $key    Original key, usually an ASIN
    *   @return string          Encoded key string to use as a cache ID
    */
    private static function _makeKey($key)
    {
        return self::$tag . '_' . md5($key);
    }


    /**
    *   Get the timestamp of the last Amazon query
    *
    *   @return integer     Timestamp value
    */
    public static function getTimestamp()
    {
        if (GVERSION < '1.8.0') {
            global $_VARS;
            $ts = $_VARS['astore_ts'];
        } else {
            $ts = (int)\glFusion\Cache::getInstance()->get('astore_ts');
        }
        if (!$ts) {
            $ts = time();
            self::setTimestamp();
        }
        return $ts;
    }


    /**
    *   Update the timestamp in cache with the current time.
    *   Expiration is not set.
    */
    public static function setTimestamp()
    {
        if (GVERSION < '1.8.0') {
            global $_TABLES, $_VARS;

            $_VARS['astore_ts'] = time();
            DB_query("UPDATE {$_TABLES['vars']}
                SET value = '{$_VARS['astore_ts']}'
                WHERE name = 'astore_ts'");
        } else {
            \glFusion\Cache::getInstance()->set('astore_ts', time(), self::$tag);
        }
    }


    /**
    *   Clear the cache, forcing future requests to be refreshed from Amazon
    */
    public static function clearCache()
    {
        if (GVERSION < '1.8.0') {
            global $_TABLES;
            DB_query("TRUNCATE {$_TABLES['astore_cache']}");
        } else {
            \glFusion\Cache::getInstance()->deleteItemsByTag(self::$tag);
        }
    }

}

?>
