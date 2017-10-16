<?php
/**
*   Class to retrieve and format Amazon Store items
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/
namespace Astore;

/**
*   Class for Amazon ItemSearch
*   @package astore
*/
class Search extends Item
{
    private $obj;   // Search results object

    /**
    *   Retrieve a single item
    *
    *   @param  string  $asin   Amazon item ID
    *   @return object          Data object
    */
    public function doSearch($query)
    {
        $retval = array();

        $md5_query = md5($query);
        $this->obj = self::_getCache($md5_query);
        if (!empty($this->obj)) {
            self::_debug("Found '$query' in cache");
        } else {
            self::_debug("Getting $query from Amazon");
            $params = array(
                'Operation' => 'ItemSearch',
                'Keywords' => urlencode($query),
                'SearchIndex' => 'All',
            );
            $this->obj = self::_makeRequest($params);
        }

        if (isset($this->obj->Items->Request->Errors->Error->Code)) {
            self::_debug('doSearch: ' . $this->obj->Error->Message, true);
        } elseif (is_object($this->obj->Items->Item)) {
            $Item = $this->obj->Items->Item;
            foreach ($Item as $info) {
                $asin = $info->ASIN->__toString();
                $retval[$asin] = new self();
                $retval[$asin]->data = $info;
            }
            self::_setCache($md5_query, ASTORE_TYPE_SEARCH, $this->obj->asXML());
        }
        return $retval;
    }


    /**
    *   Get the total number of items returned by the search
    *
    *   @return integer     Number of items, zero on error
    */
    public function Count()
    {
        if (isset($this->obj->Items->TotalResults)) {
            $retval = $this->obj->Items->TotalResults;
        } else{
            $retval = 0;
        }
        return $retval;
    }


    /**
    *   Get the total number of pages for pagination
    *
    *   @return integer     Number of pages, zero on error
    */
    public function Pages()
    {
        if (isset($this->obj->Items->TotalPages)) {
            $retval = $this->obj->Items->TotalPages;
        } else{
            $retval = 0;
        }
        return $retval;
    }


    /**
    *   Get the Amazon URL to More Results
    *
    *   @return string  URL, NULL if not set
    */
    public function MoreResultsURL()
    {
        if (isset($this->obj->Items->MoreSearchResultsUrl)) {
            return $this->obj->Items->MoreSearchResultsUrl;
        } else {
            return NULL;
        }
    }

}

?>
