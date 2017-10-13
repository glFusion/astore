<?php
/**
*   Class to retrieve and format Amazon Store items
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017 Lee Garner <lee@leegarner.com>
*   @package    classifieds
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Astore;

/**
*   Class for Amazon Items
*   @package astore
*/
class Item extends Common
{

    public function __construct($asin='')
    {
        $this->asin = $asin;
        if (!empty($this->asin)) {
            $this->data = self::Retrieve($asin);
        }
    }


    /**
    *   Retrieve a single item
    *
    *   @param  string  $asin   Amazon item ID
    *   @return object          Data object
    */
    public static function Retrieve($asin)
    {
        global $_CONF_ASTORE;

        // Return from cache if found and not expired
        $data = self::_getCache($asin);
        if (empty($data)) {
            $data = self::_getAmazon(array($asin));
            if (!empty($data) && $_CONF_ASTORE['auto_add_catalog']) {
                self::AddToCatalog($asin);
            }
            return $data[$asin];
        } else {
            return $data;
        }
    }


    /**
    *   Request item information from Amazon
    *
    *   @param  array   $params Request parameters
    *   @return string      XML response content
    */
    protected static function _getAmazon($asins)
    {
        global $_CONF_ASTORE;

        $retval = array();

        if (is_array($asins)) {
            if (count($asins) > ASTORE_MAX_QUERY) {
                // Amazon only allows 10 ASINs in a query
                array_splice($asins, 0, ASTORE_MAX_QUERY);
            }
            $asins = implode(',', $asins);
        }

        self::_debug("Getting $asins from Amazon");
        $params = array(
            'Operation' => 'ItemLookup',
            'ItemId' => $asins,
            'IdType' => 'ASIN',
        );

        $obj = self::_makeRequest($params);
        if (isset($obj->Error->Code)) {
            self::_debug($asin . ': ' . $obj->Error->Message, true);
        } else {
            $Item = $obj->Items->Item;
            foreach ($Item as $i) {
                $asin = $i->ASIN->__toString();
                self::_setCache($asin, $i->asXML());
                $retval[$asin] = $i;
            }
        }
        return $retval;
    }


    /**
    *   Get an array of all item objects
    *
    *   @param  integer $page   Page number of display
    *   @return array       Array of item objects
    */
    public static function getAll($page = 1)
    {
        global $_TABLES, $_CONF_ASTORE;

        static $allitems = NULL;
        if ($allitems === NULL) {
            $allitems = array();
            $limit = (int)$_CONF_ASTORE['perpage'];
            $start = ((int)$page - 1) * $limit;
            switch ($_CONF_ASTORE['sort']) {
            case 'rand':
                $orderby = 'RAND()';
                break;
            case 'lifo':
                $orderby = 'ts DESC';
                break;
            case 'fifo':
                $orderby = 'ts ASC';
                break;
            case 'none':
            default:
                $orderby = '';
                break;
            }
            if ($orderby != '') $orderby = "ORDER BY $orderby";
            $sql = "SELECT asin FROM {$_TABLES['astore_catalog']}
                    $orderby
                    LIMIT $start, $limit";
            //echo $sql;die;
            $res = DB_query($sql);
            $asins = array();
            while ($A = DB_fetchArray($res, false)) {
                $allitems[$A['asin']] = new self();
                $data = self::_getCache($A['asin']);
                if ($data) {
                    $allitems[$A['asin']] = new self();
                    $allitems[$A['asin']]->data = $data;
                } else {
                    // Item not in cache, add to list to get from Amazon
                    $asins[] = $A['asin'];
                }
            }
            foreach (self::$required_asins as $asin) {
                if (!isset($allitems[$asin])) {
                    // Push requested ASINs to the beginning
                    array_unshift($asins, $asin);
                }
            }
            // Retrieve from Amazon any items not in cache
            if (!empty($asins)) {
                $data = self::_getAmazon($asins);
                foreach ($data as $asin=>$info) {
                    $allitems[$asin] = new self();
                    $allitems[$asin]->data = $info;
                    if ($_CONF_ASTORE['auto_add_catalog']) {
                        // Automatically add featured items to catalog
                        self::AddToCatalog($asin);
                    }
                }
            }
        }
        return $allitems;
    }


    /**
    *   Get a specific set of ASINs from Amazon.
    *   This is intended for the admin interface to import items.
    *   Only retrieves 10 items due to Amazon's limit. Returns a string
    *   containing items that were not retrieved.
    *
    *   @param  mixed   $asins  CSV String or Array of items
    *   @param  boolean $tocatalog  True to force entry in the catalog
    *   @return string      CSV string of items not retrieved
    */
    public static function getSpecific($asins, $tocatalog=false)
    {
        global $_CONF_ASTORE;

        if (!is_array($asins)) {
            $asins = explode(',', $asins);
        }
        $bad = array();
        if (count($asins) > ASTORE_MAX_QUERY) {
            $bad = $asins;
            $bad = array_splice($bad, ASTORE_MAX_QUERY);
            array_splice($asins, 0, ASTORE_MAX_QUERY);
        }
        $asins = implode(',', $asins);

        // Retrieve from Amazon any items not in cache
        if (!empty($asins)) {
            $data = self::_getAmazon($asins);
            foreach ($data as $asin=>$info) {
                $allitems[$asin] = new self();
                $allitems[$asin]->data = $info;
                if ($_CONF_ASTORE['auto_add_catalog'] || $tocatalog) {
                    // Automatically add items to catalog
                    self::AddToCatalog($asin);
                }
            }
        }
        return implode(',', $bad);
    }


    /**
    *   Delete an item from the catalog
    *
    *   @param  string  @asin   Item number
    */
    public static function Delete($asin)
    {
        global $_TABLES;

        DB_delete($_TABLES['astore_catalog'], 'asin', $asin);
        DB_delete($_TABLES['astore_cache'], 'asin', $asin);
    }

    /**
    *   Get the number of items in the catalog.
    *   Used for pagination
    *
    *   @return integer     Count of items in the catalog table
    */
    public static function Count()
    {
        return parent::CatalogCount();
    }

}

?>
