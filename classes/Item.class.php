<?php
/**
 * Common elements for Amazon Items.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2018 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore;

/**
 * Class for Amazon Items.
 * @package astore
 */
class Item
{
    /** Amazon web services URL.
     * @var string */
    protected static $endpoint = 'webservices.amazon.com';

    /** Amazon web services URI, append to $endpoint.
     * @var string */
    protected static $uri = '/onca/xml';

    /** Cache tag applied to all cached items from this plugin.
     * @var string */
    protected static $tag = 'astore';

    /** Data holder for search results.
     * @var object */
    protected $data;

    /** Amazon ASIN number.
     * @var string */
    public $asin;

    /** ASINs that are required for display.
     * @var array */
    protected static $required_asins = array();


    /**
     * Constructor. Sets up internal variables.
     *
     * @param   string  $asin   Optional ASIN to fetch
     * @param   mixed   $data   Optional data to load into object
     */
    public function __construct($asin='', $data = '')
    {
        $this->asin = $asin;
        if (!empty($this->asin)) {
            // If data is provided, just use it. Otherwise load from catalog.
            if (!empty($data)) {
                $this->data = $data;
            } else {
                $this->data = self::Retrieve($asin);
            }
        }
    }


    /**
     * Return the raw data object.
     *
     * @return  object  simpleXML object containing item data
     */
    public function Data()
    {
        return $this->data;
    }


    /**
     * Return the ASIN, to allow public access to it.
     *
     * @return  string  Item ID
     */
    public function ASIN()
    {
        if (isset($this->data->ASIN)) {
            return $this->data->ASIN;
        } else {
            return '';
        }
    }


    /**
     * Actually make the request to Amazon.
     *
     * @param   array   $params     Paramaters to merge with the basics
     * @return  object      SimpleXML object with the results
     */
    protected static function _makeRequest($params)
    {
        global $_CONF_ASTORE;

        if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            return NULL;
        }

        // Make sure a request hasn't been made within the last second
        if (Cache::getTimestamp() >= (time() - 1)) {
            sleep(1);
        }

        $base_params = array(
            'Service' => 'AWSECommerceService',
            'AWSAccessKeyId' => $_CONF_ASTORE['aws_access_key'],
            'AssociateTag' => $_CONF_ASTORE['aws_assoc_id'],
            'ResponseGroup' => 'Images,ItemAttributes,Offers,EditorialReview',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        );
        $params = array_merge($base_params, $params);
        ksort($params);
        $pairs = array();
        foreach ($params as $key=>$value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $query_string = implode('&', $pairs);
        $string_to_sign = "GET\n".self::$endpoint."\n".self::$uri."\n".$query_string;
        $signature = base64_encode(hash_hmac('sha256', $string_to_sign,
                self::_secretKey(), true));
        $request_url = 'http://'.self::$endpoint.self::$uri.'?'.$query_string.
                '&Signature='.rawurlencode($signature);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $responseContent = curl_exec($ch);
        $responseHeaders = curl_getinfo($ch);
        curl_close($ch);
        Cache::setTimestamp();

        return self::_XmlToJson($responseContent);
    }


    /**
     * Determine if the current item had an error or is empty.
     *
     * @return  boolean     True if an error exists, False if OK
     */
    public function isError()
    {
        if (empty($this->data)) {
            return true;
        } elseif (isset($this->data->Items->Request->Errors->Error->Code)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Add an item to the catalog if not already present.
     *
     * @param   string  $asin   Item number
     * @param   string  $title  Item title to store in catalog
     * @return  boolean         True on success, False on DB error
     */
    public static function AddToCatalog($asin, $title)
    {
        global $_TABLES;

        $sql = "INSERT IGNORE INTO {$_TABLES['astore_catalog']} SET
                asin = '" . DB_escapeString($asin) . "',
                title = '" . DB_escapeString($title) . "'";
        DB_query($sql);
        return DB_error() ? false : true;
    }


    /**
     * Add an item to the $required_asins array so it will be retrieved.
     *
     * @param   string  $asin   Item number
     */
    public static function RequireASIN($asin)
    {
        self::$required_asins[$asin] = $asin;
    }


    /**
     * Log a debug message if aws_debug is enabled.
     *
     * @param   string  $text   Message to be logged
     * @param   boolean $force  True to log message regardless of debug setting
     */
    protected static function _debug($text, $force = false)
    {
        global $_CONF_ASTORE;

        if ($force || (isset($_CONF_ASTORE['debug_aws']) && $_CONF_ASTORE['debug_aws'])) {
            COM_errorLog('Astore:: ' . $text);
        }
    }


    /**
     * Decrypt the AWS secret key from the configuration.
     *
     * @return  string      Decrypted key
     */
    private static function _secretKey()
    {
        global $_CONF_ASTORE, $_VARS;
        static $secretkey = NULL;

        if ($secretkey === NULL) {
            if (isset($_VARS['guid']) && version_compare(GVERSION, '2.0.0', '<')) {
                $secretkey = COM_decrypt($_CONF_ASTORE['aws_secret_key'], $_VARS['guid']);
            } else {
                $secretkey = $_CONF_ASTORE['aws_secret_key'];
            }
        }
        return $secretkey;
    }


    /**
     * Determine if an item is available at all from Amazon.
     * Returns False if not available from Amazon nor from other sellers.
     *
     * @return  boolean     True if available, False if not
     */
    public function isAvailable()
    {
        $retval = true;
        if (isset($this->data->Offers->TotalOffers)) {
            $x = (int)$this->data->Offers->TotalOffers;
            if ($x == 0) {
                if (!isset($this->data->OfferSummary->LowestNewPrice)) {
                    $retval = false;
                }
            }
        }
        return $retval;
    }


    /**
     * Determine if this item is being sold by Amazon.
     *
     * @return  boolean True if available, False if sold only by others
     */
    private function _haveAmazonOffers()
    {
        if (!isset($this->data->Offers->TotalOffers)) {
            return false;
        } else {
            $x = $this->data->Offers->TotalOffers;
            if (!$x)
                return false;
        }
        return true;
    }


    /**
     * Get the More Offers URL for an item.
     * Returns a URL, or an empty string if the item is not available or is
     * sold by Amazon.
     *
     * @return  boolean     URL or empty string
     */
    public function OffersURL()
    {
        $retval = NULL;
        if ($this->isAvailable() && $this->_haveAmazonOffers() &&
                isset($this->data->ItemLinks->ItemLink[6]) ) {
            $retval = $this->data->ItemLinks->ItemLink[6]->URL;
        }
        return self::stripAWStag($retval);
    }


    /**
     * Get the editorial review content for the item.
     *
     * @return  string      Editorial review content
     */
    public function EditorialReview()
    {
        if (isset($this->data->EditorialReviews->EditorialReview->Content)) {
            return $this->data->EditorialReviews->EditorialReview->Content;
        } else {
            return '';
        }
    }


    /**
     * Get the feature list for the item.
     *
     * @return  string  Feature list
     */
    public function Features()
    {
        if (isset($this->data->ItemAttributes->Feature)) {
            return $this->data->ItemAttributes->Feature;
        } else {
            return '';
        }
    }


    /**
     * Get the display price for the item.
     * Gets the lowest price if available.
     *
     * @param   string  $fmt    'formatted', 'raw', 'amount'
     * @return  mixed       String or numeric price
     */
    public function DisplayPrice($fmt = 'formatted')
    {
        $p = $this->LowestPrice($fmt);
        if (empty($p)) {
            $p = $this->ListPrice($fmt);
        }
        return $p;
    }


    /**
     * Get the lowest price for this item
     *
     * @param   string  $fmt    'formatted', 'raw', 'amount'
     * @return  mixed       String or numeric price
     */
    public function LowestPrice($fmt = 'formatted')
    {
        switch ($fmt) {
        case 'formatted':
            $p = $this->data->OfferSummary->LowestNewPrice->FormattedPrice;
            break;
        case 'raw':
        case 'amount':
            $p = $this->data->OfferSummary->LowestNewPrice->Amount;
            break;
        }
        return $p;
    }


    /**
     * Get the list price for this item.
     *
     * @param   string  $fmt    'formatted', 'raw', 'amount'
     * @return  mixed       String or numeric price
     */
    public function ListPrice($fmt = 'formatted')
    {
        $p = 'N/A';
        switch ($fmt) {
        case 'formatted':
            if (isset($this->data->ItemAttributes->ListPrice->FormattedPrice)) {
                $p = $this->data->ItemAttributes->ListPrice->FormattedPrice;
            }
            break;
        case 'raw':
        case 'amount':
            if (isset($this->data->ItemAttributes->ListPrice->Amount)) {
                $p = $this->data->ItemAttributes->ListPrice->Amount;
            }
            break;
        }
        return $p;
    }


    /**
     * Get the title string for this item.
     *
     * @return  string      Item title
     */
    public function Title()
    {
        if (isset($this->data->ItemAttributes->Title)) {
            return $this->data->ItemAttributes->Title;
        } else {
            return '';
        }
    }


    /**
     * Get the URL to the detail page on Amazon.
     *
     * @return  string      Detail page URL
     */
    public function DetailPageUrl()
    {
        if (isset($this->data->DetailPageURL)) {
            return self::stripAWStag($this->data->DetailPageURL);
        } else {
            return '';
        }
    }


    /**
     * Get the URL to the small image for this item.
     *
     * @return  string  Image URL
     */
    public function SmallImage()
    {
        if (!isset($this->data->ImageSets->ImageSet)) {
            return '';
        }

        $img = $this->data->ImageSets->ImageSet;
        if (is_array($img)) {
            return $img[0]->SmallImage;
        } else {
            return $img->SmallImage;
        }
    }


    /**
     * Get the URL to the medium-sized image for this item.
     *
     * @return  string  Image URL
     */
    public function MediumImage()
    {
        if (!isset($this->data->ImageSets->ImageSet)) {
            return '';
        }

        $img = $this->data->ImageSets->ImageSet;
        if (is_array($img)) {
            return $img[0]->MediumImage;
        } else {
            return $img->MediumImage;
        }
    }


    /**
     * Get the URL to the large image for this item.
     *
     * @return  string  Image URL
     */
    public function LargeImage()
    {
        if (!isset($this->data->ImageSets->ImageSet)) {
            return '';
        }

        $img = $this->data->ImageSets->ImageSet;
        if (is_array($img)) {
            return $img[0]->LargeImage;
        } else {
            return $img->LargeImage;
        }
    }


    /**
     * Get the similar products to this item.
     *
     * @return  array   Array of similar product ASINs
     */
    public function Similar()
    {
        if (isset($this->data->SimilarProducts)) {
            return $this->data->SimilarProducts;
        } else {
            return '';
        }
    }


    /**
     * Check if this item is eligible for Amazon Prime.
     *
     * return   integer     Zero if not elibible, Nonzero if eligible
     */
    public function isPrime()
    {
        if (isset($this->data->Offers->Offer->OfferListing->IsEligibleForPrime)) {
            return (int)$this->data->Offers->Offer->OfferListing->IsEligibleForPrime;
        } else {
            return 0;
        }
    }


    /**
     * Display the products in a grid.
     *
     * @param   array   $items  Array of item objects
     * @return  string      HTML for the product page
     */
    public static function showProducts($items)
    {
        global $_CONF_ASTORE;

        if (!is_array($items)) {
            $items = array($items);
        }
        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file(array(
            'products' => 'productbox.thtml',
        ) );
        $T->set_block('products', 'productbox', 'pb');
        foreach ($items as $item) {
            if ($item->isError()) continue;
            if (!$item->isAvailable()) {
                $item->Disable();
                continue;
            }
            $T->set_var(array(
                'item_url'  => $item->DetailPageURL(),
                'lowestprice'   => $item->LowestPrice(),
                'listprice' => $item->ListPrice(),
                'title'     => COM_truncate($item->Title(),
                        $_CONF_ASTORE['max_blk_desc'], '...'),
                'img_url'   => $item->MediumImage()->URL,
                'img_width' => $item->MediumImage()->Width,
                'img_height' => $item->MediumImage()->Height,
                'formattedprice' => $item->LowestPrice(),
                'displayprice' => $item->DisplayPrice(),
                'iconset'   => $_CONF_ASTORE['_iconset'],
                'long_description' => '',
                'offers_url' => $item->OffersURL(),
                'available' => $item->isAvailable(),
                'is_prime' => $item->isPrime() ? true : false,
            ) );
            $T->parse('pb', 'productbox', true);
        }
        $T->parse('output', 'products');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Retrieve a single item.
     *
     * @param   string  $asin   Amazon item ID
     * @return  object          Data object
     */
    public static function Retrieve($asin)
    {
        global $_CONF_ASTORE;

        // Return from cache if found and not expired
        $data = Cache::get($asin);
        if (empty($data)) {
            $data = self::_getAmazon(array($asin));
            if (!empty($data) && $_CONF_ASTORE['auto_add_catalog']) {
                if (isset($data->ItemAttributes->Title)) {
                    $title = $data->ItemAttributes->Title;
                } else {
                    $title = '';
                }
                self::AddToCatalog($asin, $title);
            }
            if (isset($data[$asin])) {
                return $data[$asin];
            } else {
                return NULL;
            }
        } else {
            return $data;
        }
    }


    /**
     * Request item information from Amazon.
     * The request type may be one of 'ASIN', 'ISBN', 'SKU', 'UPC', 'EAN'.
     *
     * @param   array   $asins  Requested ASIN numbers
     * @param   string  $type   Type of item number (ASIN or ISBN)
     * @return  array   Array of Item objects
     */
    protected static function _getAmazon($asins, $type='ASIN')
    {
        global $_CONF_ASTORE;

        $retval = array();
        if (empty($asins)) return $retval;

        if (is_array($asins)) {
            if (count($asins) > ASTORE_MAX_QUERY) {
                // Amazon only allows 10 ASINs in a query
                array_splice($asins, 0, ASTORE_MAX_QUERY);
            }
            $asins = implode(',', $asins);
        }

        self::_debug("Getting $asins from Amazon");
        $type = strtoupper($type);
        switch ($type) {
        case 'ASIN':
        case 'ISBN':
        case 'SKU':
        case 'UPC':
        case 'EAN':
            break;
        default:
            COM_errorLog("invalid item ID type '$type' for items " . print_r($asins,true));
            return $retval;
        }
        $params = array(
            'Operation' => 'ItemLookup',
            'ItemId' => $asins,
            'IdType' => $type,
        );
        if ($type != 'ASIN') {
            $params['SearchIndex'] = 'All';
        }

        $obj = self::_makeRequest($params);
        if (!is_object($obj)) return $retval;
        if (isset($obj->Items->Request->Errors->Error->Code)) {
            self::_debug($asins . ': ' . $obj->Items->Request->Errors->Error->Message, true);
        } elseif (is_array($obj->Items->Item)) {
            $Item = $obj->Items->Item;
            foreach ($Item as $i) {
                Cache::set($i->ASIN, $i);
                $retval[$i->ASIN] = $i;
            }
        } elseif (is_object($obj->Items->Item)) {
            $i = $obj->Items->Item;
            Cache::set($i->ASIN, $i);
            $retval[$i->ASIN] = $i;
        }
        return $retval;
    }


    /**
     * Get an array of all item objects from the catalog.
     *
     * @param   integer $page       Page number of display
     * @param   boolean $enabled    True to only get enabled items
     * @return  array       Array of item objects
     */
    public static function getAll($page = 1, $enabled = true)
    {
        global $_TABLES, $_CONF_ASTORE;

        $allitems = array();
        $orderby = '';
        switch ($_CONF_ASTORE['sort']) {
        case 'rand':
            $orderby = 'RAND()';
            break;
        case 'lifo':
            $orderby = 'ts DESC, asin DESC';
            break;
        case 'fifo':
            $orderby = 'ts ASC, asin ASC';
            break;
        case 'none':
        default:
            $orderby = '';
            break;
        }
        $where = $enabled ? ' WHERE enabled = 1' : '';
        if ($orderby != '') $orderby = "ORDER BY $orderby";
        if ($page > 0) {
            $max = (int)$_CONF_ASTORE['perpage'];
            $start = ((int)$page - 1) * $max;
            $limit = "LIMIT $start, $max";
        } else {
            $limit = '';
        }
        $sql = "SELECT asin FROM {$_TABLES['astore_catalog']}
            $where $orderby $limit";
        //echo $sql;die;
        $res = DB_query($sql);
        $asins = array();
        while ($A = DB_fetchArray($res, false)) {
            $data = Cache::get($A['asin']);
            if ($data) {
                $allitems[$A['asin']] = new self($A['asin'], $data);
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
                    if (isset($info->ItemAttributes->Title)) {
                        $title = $info->ItemAttributes->Title;
                    } else {
                        $title = '';
                    }
                    self::AddToCatalog($asin, $title);
                }
            }
        }
        return $allitems;
    }


    /**
     * Get a specific set of ASINs from Amazon.
     * This is intended for the admin interface to import items.
     * Only retrieves 10 items due to Amazon's limit. Returns a string
     * containing items that were not retrieved.
     *
     * @param   mixed   $asins      CSV String or Array of items
     * @param   boolean $tocatalog  True to force entry in the catalog
     * @return  string      CSV string of items not retrieved
     */
    public static function importItems($asins, $tocatalog=false)
    {
        global $_CONF_ASTORE;

        if (!is_array($asins)) {
            $asins = explode(',', $asins);
        }
        $bad = array();
        if (count($asins) > ASTORE_MAX_QUERY) {
            $bad = $asins;
            array_splice($bad, 0, ASTORE_MAX_QUERY);
            array_splice($asins, ASTORE_MAX_QUERY);
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
                    if (isset($info->ItemAttributes->Title)) {
                        $title = $info->ItemAttributes->Title;
                    } else {
                        $title = '';
                    }
                    self::AddToCatalog($asin, $title);
                }
            }
        }
        // Return the remaining ASINs to get with the next request
        return implode(',', $bad);
    }


    /**
     * Export all catalog items as a CSV, ready to be imported.
     *
     * @return  string  CSV string containing all item IDs
     */
    public static function exportItems()
    {
        global $_TABLES;

        $sql = "SELECT asin FROM {$_TABLES['astore_catalog']}";
        $res = DB_query($sql);
        $items = array();
        while ($A = DB_fetchArray($res, false)) {
            $items[] = $A['asin'];
        }
        return implode(',', $items);
    }


    /**
     * Delete an item from the catalog and cache.
     *
     * @param   string  $asin   Item number
     */
    public static function Delete($asin)
    {
        global $_TABLES;

        DB_delete($_TABLES['astore_catalog'], 'asin', $asin);
        DB_delete($_TABLES['astore_cache'], 'asin', $asin);
    }


    /**
     * Get the number of items in the catalog.
     * Used for pagination.
     *
     * @param   boolean $enabled    True to count only enabled items
     * @return  integer     Count of items in the catalog table
     */
    public static function Count($enabled = true)
    {
        global $_TABLES;
        static $counts = array();
        if ($enabled) {
            $fld = 'enabled';
            $value = 1;
            $key = 1;
        } else {
            $fld = '';
            $value = '';
            $key = 0;
        }
        if (!isset($counts[$key])) {
            $counts[$key] = (int)DB_count($_TABLES['astore_catalog'], $fld, $value);
        }
        return $counts[$key];
    }


    /**
     * Get the number of pages.
     *
     * @return  integer     Number of pages
     */
    public function Pages()
    {
        global $_CONF_ASEARCH;

        $count = self::Count();

        if (!isset($_CONF_ASTORE['perpage']) ||
            $_CONF_ASTORE['perpage'] < 1) {
            $_CONF_ASTORE['perpage'] = 10;
        }
        return ceil($count / $_CONF_ASTORE['perpage']);
    }


    /**
     * Get an item by ISBN.
     *
     * @param   string  $isbn   ISBN number
     * @return  array   $data   Array of data if found.
     */
    public static function getByISBN($isbn)
    {
        $data = Cache::get($isbn);
        if ($data === NULL) {
            $data = self::_getAmazon($isbn, 'ISBN');
            Cache::set($isbn, $data);
        }
        return $data;
    }


    /**
     * Remove any associate-related tags from the product URL for admins.
     * This is to avoid artifically inflating the click count at Amazon
     * during testing by admins.
     * If the configured header is present, or an admin is logged in and
     * admins should not see associate links, then strip the associate infl.
     *
     * @param   string  $url    Product URL
     * @return  string          URL without associate tags
     */
    private static function stripAWStag($url)
    {
        global $_CONF_ASTORE;

        if (($_CONF_ASTORE['notag_header'] != '' &&
            isset($_SERVER['HTTP_' . strtoupper($_CONF_ASTORE['notag_header'])])) ||
            $_CONF_ASTORE['notag_admins'] && plugin_ismoderator_astore()) {
            return preg_replace('/\?.*/', '', $url);
        } else {
            return $url;
        }
    }


    /**
     * Convert the XML received from Amazon to JSON.
     *
     * @param   string  $str    XML string
     * @return  object          JSON object
     */
    private static function _XmlToJson($str)
    {
        $str = str_replace(array("\n", "\r", "\t"), '', $str);
        $str = trim(str_replace('"', "'", $str));
        $simpleXml = simplexml_load_string($str);
        $json = json_encode($simpleXml);
        $json = json_decode($json); // back to object
        return $json;
    }


    /**
     * Disable a catalog item when it is unavailable.
     *
     * @uses    self::toggle()
     */
    private function Disable()
    {
        self::toggle(1, 'enabled', $this->asin);
    }


    /**
     * Toggle a field in the catalog.
     *
     * @param   integer $oldval     Original value to be changed
     * @param   string  $field      Field name
     * @param   string  $asin       Item ID
     * @return  integer     New value, or old value in case of error
     */
    public static function toggle($oldval, $field, $asin)
    {
        global $_TABLES;

        $oldval = $oldval == 0 ? 0 : 1;
        $newval = $oldval == 0 ? 1 : 0;
        $field = DB_escapeString($field);
        $asin = DB_escapeString($asin);
        $sql = "UPDATE {$_TABLES['astore_catalog']}
            SET $field = $newval
            WHERE asin = '$asin'";
        DB_query($sql);
        if (DB_error()) {
            return $oldval;
        } else {
            return $newval;
        }
    }

}

?>
