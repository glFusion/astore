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
class Item
{
    private static $endpoint = 'webservices.amazon.com';
    private static $uri = '/onca/xml';
    private $data;
    private $asin;
    private static $required_asins = array();

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
            if ($_CONF_ASTORE['feat_to_catalog']) {
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
    private static function _getAmazon($asins)
    {
        global $_CONF_ASTORE;

        $now = time();
        if (self::_getTimestamp() >= $now) {
            sleep(1);
        }
        if (is_array($asins)) {
            $asins = implode(',', $asins);
        }

        self::_debug("Getting $asins from Amazon");
        $params = array(
            'Service' => 'AWSECommerceService',
            'Operation' => 'ItemLookup',
            'AWSAccessKeyId' => $_CONF_ASTORE['aws_access_key'],
            'AssociateTag' => $_CONF_ASTORE['aws_assoc_id'],
            'ItemId' => $asins,
            'IdType' => 'ASIN',
            'ResponseGroup' => 'Images,ItemAttributes,Offers,EditorialReview',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        //    'Timestamp' => '2017-10-09T15:30:56Z',
        );
        ksort($params);
        $pairs = array();
        foreach ($params as $key=>$value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $query_string = implode('&', $pairs);
        $string_to_sign = "GET\n".self::$endpoint."\n".self::$uri."\n".$query_string;
        $signature = base64_encode(hash_hmac('sha256', $string_to_sign,
                self::_secretKey(), true));
        //        $_CONF_ASTORE['aws_secret_key'], true));
        $request_url = 'http://'.self::$endpoint.self::$uri.'?'.$query_string.
                '&Signature='.rawurlencode($signature);

        if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            throw new \Exception("cURL support is required, but can't be found.");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $request_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $responseContent     = curl_exec($ch);
        $response['headers'] = curl_getinfo($ch);
        curl_close($ch);
        self::_setTimestamp();

        $obj = new \SimpleXMLElement($responseContent);
        $retval = array();

        if (isset($obj->Error->Code)) {
            COM_errorLog($asin . ': ' . $obj->Error->Message);
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
    *   Get item information from the cache, if present
    *
    *   @param  string  $asin   Item number
    *   @return mixed       Item object, NULL if not present
    */
    private static function _getCache($asin)
    {
        global $_TABLES;

        $asin = DB_escapeString($asin);
        $data = DB_getItem($_TABLES['astore_cache'], 'data',
            "asin = '$asin' AND exp > UNIX_TIMESTAMP()");
        if (!empty($data)) $data = simplexml_load_string($data);
        return $data;
    }


    /**
    *   Sets an item's data into the cache
    *
    *   @param  string  $asin   Item number
    *   @param  string  $data   XML data
    */
    private static function _setCache($asin, $data)
    {
        global $_TABLES, $_CONF_ASTORE;

        $cache_secs = (int)$_CONF_ASTORE['cache_min'];
        if ($cache_secs < 600) $cache_secs = 1800;
        $asin = DB_escapeString($asin);
        $data = DB_escapeString($data);
        $sql = "INSERT INTO {$_TABLES['astore_cache']} SET
                    asin = '$asin',
                    data = '$data',
                    exp = UNIX_TIMESTAMP() + $cache_secs
                ON DUPLICATE KEY UPDATE
                    data = '$data',
                    exp = UNIX_TIMESTAMP() + $cache_secs";
        //echo $sql;die;
        DB_query($sql);
    }


    /**
    *   Calculate the new dimensions needed to keep the image within
    *   the provided width & height while preserving the aspect ratio.
    *
    *   @param  string  $origpath   Original image path
    *   @param  integer $width      New width, in pixels
    *   @param  integer $height     New height, in pixels
    *   @return mixed       array of dimensions, or false on error
    */
    public static function reDim($width, $height)
    {
        // get both sizefactors that would resize one dimension correctly
        if ($width > 0 && $s_width > $width)
            $sizefactor_w = (double)($width / $s_width);
        else
            $sizefactor_w = 1;

        if ($height > 0 && $s_height > $height)
            $sizefactor_h = (double)($height / $s_height);
        else
            $sizefactor_h = 1;

        // Use the smaller factor to stay within the parameters
        $sizefactor = min($sizefactor_w, $sizefactor_h);

        $newwidth = (int)($s_width * $sizefactor);
        $newheight = (int)($s_height * $sizefactor);

        return array(
            's_width'   => $s_width,
            's_height'  => $s_height,
            'd_width'   => $newwidth,
            'd_height'  => $newheight,
            'mime'      => $mime_type,
        );
    }


    public function FormattedPrice()
    {
        return $this->data->OfferSummary->LowestNewPrice->FormattedPrice;
    }
    public function ListPrice()
    {
        return $this->data->OfferSummary->LowestNewPrice->FormattedPrice;
    }
    public function Title()
    {
        return $this->data->ItemAttributes->Title;
    }
    public function DetailUrl()
    {
        return $this->data->DetailPageURL;
    }
    public function SmallImage()
    {
        return $this->data->SmallImage;
    }
    public function MediumImage()
    {
        return $this->data->MediumImage;
    }
    public function OffersURL()
    {
        return $this->data->Offers->MoreOffersUrl;
    }
    public function EditorialReview()
    {
        return $this->data->EditorialReviews->EditorialReview->Content;
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
            $sql = "SELECT asin FROM {$_TABLES['astore_catalog']}
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
                if (!isset($allitems[$asin])) $asins[] = $asin;
            }
            // Retrieve from Amazon any items not in cache
            if (!empty($asins)) {
                $data = self::_getAmazon($asins);
                foreach ($data as $asin=>$info) {
                    $allitems[$asin] = new self();
                    $allitems[$asin]->data = $info;
                    if ($_CONF_ASTORE['feat_to_catalog']) {
                        // Automatically add featured items to catalog
                        self::AddToCatalog($asin);
                    }
                }
            }
        }
        return $allitems;
    }


    /**
    *   Add an item to the catalog if not already present
    *
    *   @param  string  $asin   Item number
    */
    public static function AddToCatalog($asin)
    {
        global $_TABLES;

        $sql = "INSERT IGNORE INTO {$_TABLES['astore_catalog']} SET
                asin = '" . DB_escapeString($asin) . "'";
        DB_query($sql);
    }


    /**
    *   Determine if the current item had an error
    *
    *   @return boolean     True if an error exists, False if OK
    */
    public function isError()
    {
        return isset($this->data->Items->Request->Errors->Error->Code) ? true : false;
    }


    /**
    *   Add an item to the $required_asins array so it will be retrieved.
    *
    *   @param  string  $asin   Item number
    */
    public static function Require($asin)
    {
        self::$required_asins[$asin] = $asin;
    }


    /**
    *   Get the number of items in the catalog.
    *   Used for pagination
    *
    *   @return integer     Count of items in the catalog table
    */
    public static function Count()
    {
        global $_TABLES;

        return (int)DB_count($_TABLES['astore_catalog']);
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
    *   Get the timestamp of the last Amazon query
    *
    *   @return integer     Timestamp value
    */
    private static function _getTimeStamp()
    {
        global $_VARS;

        return (int)$_VARS['astore_ts'];
    }


    /**
    *   Update the timestamp variable with the current time
    */
    private static function _setTimestamp()
    {
        global $_TABLES, $_VARS;

        $_VARS['astore_ts'] = time();
        DB_query("UPDATE {$_TABLES['vars']}
                SET value = '{$_VARS['astore_ts']}'
                WHERE name = 'astore_ts'");
    }


    /**
    *   Log a debug message if aws_debug is enabled
    *
    *   @param  string  $text   Message to be logged
    */
    private static function _debug($text)
    {
        global $_CONF_ASTORE;

        if ($_CONF_ASTORE['debug_aws']) {
            COM_errorLog($text);
        }
    }


    /**
    *   Decrypt the AWS secret key
    *
    *   @return string      Decrypted key
    */
    private static function _secretKey()
    {
        global $_CONF_ASTORE, $_VARS;
        static $secretkey = NULL;

        if ($secretkey === NULL) {
            if (isset($_VARS['guid'])) {
                $secretkey = COM_decrypt($_CONF_ASTORE['aws_secret_key'], $_VARS['guid']);
            } else {
                $secretkey = $_CONF_ASTORE['aws_secret_key'];
            }
        }
        return $secretkey;
    }

}

?>
