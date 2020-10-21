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

    /** Zone information for regions.
     * @var array */
    private static $zoneinfo = array(
        'au' => array(
            'host' => 'webservices.amazon.com.au',
            'region' => 'us-west-2',
        ),
        'br' => array(
            'host' => 'webservices.amazon.com.br',
            'region' => 'us-east-1',
        ),
        'ca' => array(
            'host' => 'webservices.amazon.ca',
            'region' => 'us-east-1',
        ),
        'fr' => array(
            'host' => 'webservices.amazon.fr',
            'region' => 'eu-west-1',
        ),
        'de' => array(
            'host' => 'webservices.amazon.de',
            'region' => 'eu-west-1',
        ),
        'in' => array(
            'host' => 'webservices.amazon.in',
            'region' => 'eu-west-1',
        ),
        'it' => array(
            'host' => 'webservices.amazon.it',
            'region' => 'eu-west-1',
        ),
        'jp' => array(
            'host' => 'webservices.amazon.co.jp',
            'region' => 'us-west-2',
        ),
        'mx' => array(
            'host' => 'webservices.amazon.com.mx',
            'region' => 'us-east-1',
        ),
        'nl' => array(
            'host' => 'webservices.amazon.nl',
            'region' => 'eu-west-1',
        ),
        'sg' => array(
            'host' => 'webservices.amazon.sg',
            'region' => 'us-west-2',
        ),
        'es' => array(
            'host' => 'webservices.amazon.es',
            'region' => 'eu-west-1',
        ),
        'tr' => array(
            'host' => 'webservices.amazon.com.tr',
            'region' => 'eu-west-1',
        ),
        'ae' => array(
            'host' => 'webservices.amazon.ae',
            'region' => 'eu-west-1',
        ),
        'uk' => array(
            'host' => 'webservices.amazon.co.uk',
            'region' => 'eu-west-1',
        ),
        'us' => array(
            'host' => 'webservices.amazon.com',
            'region' => 'us-east-1',
        ),
    );


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


    private function _getRegion()
    {
        global $_CONF_ASTORE;
        return self::$zoneinfo[$_CONF_ASTORE['aws_region']]['region'];
    }


    private function _getHost()
    {
        global $_CONF_ASTORE;
        return self::$zoneinfo[$_CONF_ASTORE['aws_region']]['host'];
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

        //self::_debug("Getting $asins from Amazon");
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
    }


    /**
     * Search Amazon for items matching a given query.
     *
     * @param   string  $query  Query string
     * @return  array   Array of Item objects
     */
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
                // We have the data, so make sure it's cached
                if (!isset($obj->_timestamp)) {
                    $obj->_timestamp = $response->_timestamp;
                }
                Cache::set($obj->ASIN, $obj);
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
            //'Marketplace' => 'www.amazon.com',
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
        $endpoint = $this->_getHost();
        $awsv4 = new AwsV4($this->access_key, $this->secret_key);
        $awsv4->setRegionName($this->_getRegion());
        $awsv4->setServiceName("ProductAdvertisingAPI");
        $awsv4->setPath ($this->path);
        $awsv4->setPayload ($payload);
        $awsv4->setRequestMethod ("POST");
        $awsv4->addHeader ('content-encoding', 'amz-1.0');
        $awsv4->addHeader ('content-type', 'application/json; charset=utf-8');
        $awsv4->addHeader ('host', $endpoint);
        $awsv4->addHeader(
            'x-amz-target',
            'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.' . $this->hdr_target
        );
        $headers = $awsv4->getHeaders ();
        $hdr_arr = array();
        foreach ($headers as $key => $value) {
            $hdr_arr[] = $key . ': ' . $value;
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_URL => $endpoint . $this->path,
            CURLOPT_USERAGENT => 'glFusion Astore',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => $hdr_arr,
            CURLOPT_POSTFIELDS => $payload,
        ) );
        $response = curl_exec($ch);
        $status = curl_getinfo($ch);
        if ($response === false) {
            COM_errorLog("Received false from $endpoint");
            COM_errorLog("request status" . print_r($status,true));
            //throw new \Exception ( "Exception Occured" );
        } else {
            $response = json_decode($response);
        }
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
