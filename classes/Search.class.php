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
class Search extends Common
{

    /**
    *   Retrieve a single item
    *
    *   @param  string  $asin   Amazon item ID
    *   @return object          Data object
    */
    public static function doSearch($query)
    {
        $retval = array();

        self::_debug("Getting $asins from Amazon");
        $params = array(
            'Service' => 'AWSECommerceService',
            'Operation' => 'ItemSearch',
            'Keywords' => urlencode($query),
            'SearchIndex' => 'All',
            'ResponseGroup' => 'Images,ItemAttributes,Offers,EditorialReview',
        );

        $obj = self::_makeRequest($params);
        if (isset($obj->Error->Code)) {
            self::_debug('doSearch: ' . $obj->Error->Message, true);
        } else {
            $Item = $obj->Items->Item;
            foreach ($Item as $info) {
                $asin = $info->ASIN->__toString();
                $retval[$asin] = new self();
                $retval[$asin]->data = $info;
                $this->data[$asin] = $info;
            }
        }
        return $retval;
    }


    public function PageCount()
    {

    }

    public function getPage()
    {
    }


}

?>
