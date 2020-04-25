<?php
/**
 * Implement the Amazon API
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore;


/**
 * Class for Amazon API v5 interface.
 * @package astore
 */
class API
{
    /** Amazon web services URL.
     * @var string */
    private static $endpoint = 'webservices.amazon.com';

    /** Data holder for search results.
     * @var object */
    private $Data = NULL;

    /** AWS Access key.
     * @var string */
    private $access_key = '';

    /** AWS Secret key.
     * @var string */
    private $secret_key = '';

    /** Path, e.g. `/paapi5/getitems`.
     * @var string */
    private $path = '/paapi5/getitems';

    /** Header target, e.g. `GetItems`.
     * @var string */
    private $hdr_target = 'GetItems';


    /**
     * Constructor. Sets up internal variables.
     *
     * @param   string  $asin   Optional ASIN to fetch
     * @param   mixed   $data   Optional data to load into object
     */
    public function __construct($asin='', $data = '')
    {
        global $_CONF_ASTORE;

        $this->access_key = $_CONF_ASTORE['aws_access_key'];
        $this->secret_key = self::_secretKey();

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
    public function getData()
    {
        return $this->Data;
    }


    /**
     * Request item information from Amazon.
     * The request type may be one of 'ASIN', 'ISBN', 'SKU', 'UPC', 'EAN'.
     *
     * @param   array   $asins  Requested ASIN numbers
     * @param   string  $type   Type of item number (ASIN or ISBN)
     * @return  array   Array of Item objects
     */
    public function getItems($asins, $type='ASIN')
    {
        global $_CONF_ASTORE;

        $retval = array();
        if (empty($asins)) return $retval;

        if (is_array($asins)) {
            if (count($asins) > ASTORE_MAX_QUERY) {
                // Amazon only allows 10 ASINs in a query
                array_splice($asins, 0, ASTORE_MAX_QUERY);
            }
            //$asins = implode(',', $asins);
        }
        if (empty($asins)) {
            return false;
        }

        self::_debug("Getting $asins from Amazon");
        $type = strtoupper($type);
        switch ($type) {
        case 'ASIN':
            break;
        case 'ISBN':        // other types not supported with APIv5
        case 'SKU':
        case 'UPC':
        case 'EAN':
        default:
            COM_errorLog("invalid item ID type '$type' for items " . print_r($asins,true));
            return $retval;
        }
        $params = array(
            'Operation' => 'ItemLookup',
            'ItemIds' => $asins,
            'ItemIdType' => $type,
        );
        if ($type != 'ASIN') {
            $params['SearchIndex'] = 'All';
        }
        $this->path = '/paapi5/getitems';
        $response = $this->_makeRequest($params);
        if (isset($response->ItemsResult->Items)) {
            foreach ($response->ItemsResult->Items as $Item) {
                $retval[$Item->ASIN] = $Item;
            }
        }
        return $retval;
        /*if (isset($obj->Items->Request->Errors->Error->Code)) {
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
        }*/
        return $retval;
    }


    public function searchItems($query)
    {
        $md5_query = md5($query);
        $response = Cache::get($md5_query);
        if (!empty($response)) {
            self::_debug("Found '$query' in cache");
        } else {
            self::_debug("Getting $query from Amazon");
            $this->path = '/paapi5/searchitems';
            $this->hdr_target = 'SearchItems';
            $params = array(
                'Operation' => 'SearchItems',
                'Keywords' => $query,
                'SearchIndex' => 'All',
            );
            $response = self::_makeRequest($params);
            if (!empty($response)) {
                Cache::set($md5_query, $response);
            }
        }
        $retval = array();
        if (isset($response->SearchResult->Items)) {
            foreach ($response->SearchResult->Items as $obj) {
                $retval[$obj->ASIN] = new Item($obj->ASIN, $obj);
            }
        }
        return $retval;
    }

    /**
     * Actually make the request to Amazon.
     *
     * @param   array   $params     Paramaters to merge with the basics
     * @return  object      SimpleXML object with the results
     */
    protected function _makeRequest($params)
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
            'Marketplace' => 'www.amazon.com',
            'LanguagesOfPreference' => array('en_US'),
            'PartnerTag' => $_CONF_ASTORE['aws_assoc_id'],
            'PartnerType' => 'Associates',
            'Resources' => array(
                'Images.Primary.Small',
                'Images.Primary.Medium',
                'Images.Primary.Large',
                'ItemInfo.Title',
                'ItemInfo.Features',
                'ItemInfo.ProductInfo',
//                'ItemInfo.TechnicalInfo.Formats',
                'ItemInfo.ManufactureInfo',
//                'ItemInfo.ManufactureInfo.Model',
                'Offers.Summaries.LowestPrice',
                'Offers.Listings.Price',
                'Offers.Listings.ProgramEligibility.IsPrimeExclusive',
                'Offers.Listings.DeliveryInfo.IsPrimeEligible',
                'Offers.Listings.Availability.Type',
                'Offers.Listings.Availability.Message',
//                'Offers.Listings.availability.MaxOrderQuantity',
            ),
        );
        $params = array_merge($base_params, $params);
        ksort($params);
        //var_dump($params);die;
        /*$pairs = array();
        foreach ($params as $key=>$value) {
            $pairs[] = rawurlencode($key) . '=' . rawurlencode($value);
        }*/
        $payload = json_encode($params);

        $awsv4 = new AwsV4();
        $awsv4->setRegionName("us-east-1");
        $awsv4->setServiceName("ProductAdvertisingAPI");
        $awsv4->setPath ($this->path);
        $awsv4->setPayload ($payload);
        $awsv4->setRequestMethod ("POST");
        $awsv4->addHeader ('content-encoding', 'amz-1.0');
        $awsv4->addHeader ('content-type', 'application/json; charset=utf-8');
        $awsv4->addHeader ('host', self::$endpoint);
        $awsv4->addHeader ('x-amz-target', 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $this->hdr_target);
        $headers = $awsv4->getHeaders ();
        
        $headerString = "";
        foreach ( $headers as $key => $value ) {
            $headerString .= $key . ': ' . $value . "\r\n";
        }
        $params = array (
            'http' => array (
                'header' => $headerString,
                'method' => 'POST',
                'content' => $payload
            )
        );

        $stream = stream_context_create($params);
        $endpoint = 'https://' . self::$endpoint . $this->path;
        $fp = @fopen($endpoint, 'rb', false, $stream);

        if (!$fp) {
            //throw new \Exception ( "Exception Occured" );
            COM_errorLog("Error making request to $endpoing");
        }
        $response = @stream_get_contents($fp);
        if ($response === false) {
            COM_errorLog("Received false from $endpoint");
            //throw new \Exception ( "Exception Occured" );
        }
        $response = json_decode($response);
        return $response;
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
                // glFusion 2.0.0 already decrypts passwd config items
                $secretkey = COM_decrypt($_CONF_ASTORE['aws_secret_key'], $_VARS['guid']);
            } else {
                $secretkey = $_CONF_ASTORE['aws_secret_key'];
            }
        }
        return $secretkey;
    }


    /**
     * Retrieve a single item.
     *
     * @param   string  $asin   Amazon item ID
     * @return  object          Data object
     */
    public static function XRetrieve($asin)
    {
        global $_CONF_ASTORE;

        // Return from cache if found and not expired
        $data = Cache::get($asin);
        if ($data === NULL) {
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
    protected static function X_getAmazon($asins, $type='ASIN')
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

        $obj = $this->_makeRequest($params);
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

}

?>
