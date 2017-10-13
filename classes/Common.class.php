<?php
/**
*   Common elements for Amazon classes
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
class Common
{
    protected static $endpoint = 'webservices.amazon.com';
    protected static $uri = '/onca/xml';
    protected $data;
    protected $asin;
    protected static $required_asins = array();

    public function Data()
    {
        return $this->data;
    }

    public function ASIN()
    {
        return $this->data->ASIN;
    }


    /**
    *   Actually make the request to Amazon
    *
    *   @param  array   $params     Paramaters to merge with the basics
    *   @return object      SimpleXML object with the results
    */
    protected static function _makeRequest($params)
    {
        global $_CONF_ASTORE;

        if (!function_exists('curl_init') || !function_exists('curl_setopt')) {
            throw new \Exception("cURL support is required, but can't be found.");
        }

        if (self::_getTimestamp() >= time() - 1) {
            sleep(1);
        }

        $base_params = array(
            'Service' => 'AWSECommerceService',
            'AWSAccessKeyId' => $_CONF_ASTORE['aws_access_key'],
            'AssociateTag' => $_CONF_ASTORE['aws_assoc_id'],
            'ResponseGroup' => 'Images,ItemAttributes,Offers,EditorialReview',
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        //    'Timestamp' => '2017-10-09T15:30:56Z',
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
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        $responseContent = curl_exec($ch);
        $responseHeaders = curl_getinfo($ch);
        curl_close($ch);
        self::_setTimestamp();

        $obj = new \SimpleXMLElement($responseContent);
        return $obj;
    }


    /**
    *   Determine if the current item had an error or is empty
    *
    *   @return boolean     True if an error exists, False if OK
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
    *   Get item information from the cache, if present
    *
    *   @param  string  $asin   Item number
    *   @return mixed       Item object, NULL if not present
    */
    protected static function _getCache($asin)
    {
        global $_TABLES;

        $asin = DB_escapeString($asin);
        $data = DB_getItem($_TABLES['astore_cache'], 'data',
            "asin = '$asin' AND exp > UNIX_TIMESTAMP()");
        if (!empty($data)) {
            $use_errors = libxml_use_internal_errors(true);
            $data = simplexml_load_string($data);
            // Check if XML translation failed
            if ($data === false) $data = NULL;
            libxml_use_internal_errors($use_errors);
        }
        return $data;
    }


    /**
    *   Sets an item's data into the cache
    *
    *   @param  string  $asin   Item number
    *   @param  string  $data   XML data
    */
    protected static function _setCache($asin, $data)
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
    *   Add an item to the catalog if not already present
    *
    *   @param  string  $asin   Item number
    *   @return boolean         True on success, False on DB error
    */
    public static function AddToCatalog($asin)
    {
        global $_TABLES;

        $sql = "INSERT IGNORE INTO {$_TABLES['astore_catalog']} SET
                asin = '" . DB_escapeString($asin) . "'";
        DB_query($sql);
        return DB_error() ? false : true;
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
    public static function CatalogCount()
    {
        global $_TABLES;

        return (int)DB_count($_TABLES['astore_catalog']);
    }


    /**
    *   Get the number of pages
    *
    *   @return integer     Number of pages
    */
    public static function PageCount()
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
    *   @param  boolean $force  True to log message regardless of debug setting
    */
    protected static function _debug($text, $force = false)
    {
        global $_CONF_ASTORE;

        if ($force || $_CONF_ASTORE['debug_aws']) {
            COM_errorLog('Astore:: ' . $text);
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


    /**
    *   Determine if an item is available at all from Amazon.
    *   Returns False if not available from Amazon nor from other sellers.
    *
    *   @return boolean     True if available, False if not
    */
    public function isAvailable()
    {
        $retval = true;
        if (isset($this->data->Offers->TotalOffers)) {
            $x = (int)$this->data->Offers->TotalOffers->__toString();
            if ($x == 0) {
                if (!isset($this->data->OfferSummary->LowestNewPrice)) {
                    $retval = false;
                }
            }
        }
        return $retval;
    }


    /**
    *   Determine if this item is being sold by Amazon
    *
    *   @return boolean True if available, False if sold only by others
    */
    private function _haveAmazonOffers()
    {
        if (!isset($this->data->Offers->TotalOffers)) {
            return false;
        } else {
            $x = $this->data->Offers->TotalOffers;
            if ($x == 0)
                return false;
        }
        return true;
    }


    /**
    *   Get the More Offers URL for an item.
    *   Returns a URL, or an empty string if the item is not available or is
    *   sold by Amazon.
    *
    *   @return boolean     URL or empty string
    */
    public function OffersURL()
    {
        $retval = NULL;
        if ($this->isAvailable() && !$this->_haveAmazonOffers() &&
                isset($this->data->ItemLinks->ItemLink[6]) ) {
            $retval = $this->data->ItemLinks->ItemLink[6]->URL->__toString();
        }
        return $retval;
    }

    public function EditorialReview()
    {
        return $this->data->EditorialReviews->EditorialReview->Content;
    }
    public function Features()
    {
        return $this->data->ItemAttributes->Feature;
    }


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
    public function ListPrice($fmt = 'formatted')
    {
        switch ($fmt) {
        case 'formatted':
            $p = $this->data->ItemAttributes->ListPrice->FormattedPrice;
            break;
        case 'raw':
        case 'amount':
            $p = $this->data->ItemAttributes->ListPrice->Amount;
            break;
        }
        return $p;
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
    public function LargeImage()
    {
        return $this->data->LargeImage;
    }
    public function Similar()
    {
        return $this->data->SimilarProducts;
    }

}

?>
