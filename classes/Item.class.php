<?php
/**
 * Common elements for Amazon Items.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2023 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.3.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore;
use glFusion\Database\Database;
use glFusion\Log\Log;


/**
 * Class for Amazon Items.
 * @package astore
 */
class Item
{
    /** Data holder for search results.
     * @var object */
    private $data;

    /** Amazon ASIN number.
     * @var string */
    private $asin = '';

    /** Record ID.
     * @var integer */
    private $id = 0;

    /** Enabled?
     * @var boolean */
    private $enabled = 1;

    /** Category ID.
     * @var integer */
    private $cat_id = 1;

    /** Item Title. Saved in the catalog to show in admin lists.
     * @var string */
    private $title = '';

    /** Flag to indicate that a valid item was retrieved.
     * @var boolean */
    private $is_valid = 0;

    /** Link to Amazon detail page.
     * Only used if the API is disabled and URLs are entered manually.
     * @var string */
    private $url = '';

    /** Editorial content, review, etc.
     * @var string */
    private $editorial = '';

    /** ASINs that are required for display.
     * @var array */
    protected static $required_asins = array();


    /**
     * Constructor. Sets up internal variables.
     *
     * @param   string  $asin   Optional ASIN to fetch
     * @param   mixed   $data   Optional data to load into object
     */
    public function __construct($asin='', $data='')
    {
        global $_CONF_ASTORE;

        if (!empty($asin)) {
            if (is_array($asin)) {
                $this->setVars($asin);
                $asin = $this->asin;
            } else {
                $this->asin = $asin;
            }

            if (is_object($data)) {
                // Got data already, probably from cache
                $this->data = $data;
            } else {
                $this->Read();
                // If data is provided, just use it. Otherwise load from catalog.
                if ($this->is_valid && $_CONF_ASTORE['use_api']) {
                    $this->data = self::Retrieve($asin);
                    if (!empty($this->data)) {
                        $this->title = $this->Title();
                    }
                }
            }
        }
    }


    /**
     * Read a single item record from the database using the current ASIN key.
     *
     * @return  object  $this
     */
    private function Read() : self
    {
        global $_TABLES;

        try {
            $row = Database::getInstance()->conn->executeQuery(
                "SELECT * FROM {$_TABLES['astore_catalog']} WHERE asin = ?",
                array($this->asin),
                array(Database::STRING)
            )->fetchAssociative();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $row = false;
        }
        if (is_array($row)) {
            $this->setVars($row);
            $this->is_valid = 1;
            $this->data = self::Retrieve($this->asin);
            if ($this->data !== NULL) {
                $this->is_valid = 1;
            }
        }
        return $this;
    }


    /**
     * Set the variables into local properties.
     *
     * @param   array   $A  Array of properties from the DB or form.
     * @return  object  $this
     */
    public function setVars($A)
    {
        $this->asin = trim($A['asin']);
        $this->title = trim($A['title']);
        if (isset($A['url'])) {
            // Only used if API is disabled
            $this->url = trim($A['url']);
        }
        $this->cat_id = (int)$A['cat_id'];
        $this->enabled = isset($A['enabled']) && $A['enabled'] ? 1 : 0;
        $this->editorial = isset($A['editorial']) ? $A['editorial'] : '';
    }


    /**
     * Set the ASIN key for this item.
     *
     * @param   string  $asin   ASIN value
     * @return   object $this
     */
    public function setASIN($asin)
    {
        $this->asin = $asin;
        return $this;
    }


    /**
     * Set the data retrieved from Amazon.
     *
     * @param   object  $data   JSON-decoded object
     * @return  object $this
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }


    /**
     * Set the item title.
     *
     * @param   string  $title  Item title
     * @return  object  $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }


    /**
     * Get an instance of an item.
     * Caching is handled in Retrieve() so no need to implement here.
     *
     * @param   string  $asin   Item ID
     * @return  object      Item object
     */
    public static function getInstance($asin)
    {
        return new self($asin);
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
        return $this->asin;
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
    public static function AddToCatalog(string $asin, string $title) : bool
    {
        global $_TABLES, $_CONF_ASTORE;

        try {
            Database::getInstance()->conn->executeStatement(
                "INSERT IGNORE INTO {$_TABLES['astore_catalog']} SET
                asin = ?, title = ?, cat_id = ?",
                array($asin, $title, $_CONF_ASTORE['def_catid']),
                array(Database::STRING, Database::STRING, Database::INTEGER)
            );
            return true;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
    }


    /**
     * Add an item to the $required_asins array so it will be retrieved.
     *
     * @param   string  $asin   Item number
     */
    public static function RequireASIN(string $asin) : void
    {
        self::$required_asins[$asin] = $asin;
    }


    /**
     * Log a debug message if aws_debug is enabled.
     *
     * @param   string  $text   Message to be logged
     * @param   boolean $force  True to log message regardless of debug setting
     */
    protected static function _debug(string $text, $force = false) : void
    {
        global $_CONF_ASTORE;

        if ($force || (isset($_CONF_ASTORE['debug_aws']) && $_CONF_ASTORE['debug_aws'])) {
            Log::write('system', Log::ERROR, 'Astore:: ' . $text);
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
        } elseif ($this->ListPrice('raw') < .01) {
            $retval = false;
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
     * Get the feature list for the item.
     *
     * @return  string  Feature list
     */
    public function Features()
    {
        if (isset($this->data->ItemInfo->Features->DisplayValues)) {
            return $this->data->ItemInfo->Features->DisplayValues;
        } else {
            return array();
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
        if (!isset($this->data->Offers)) {
            return '';
        }

        switch ($fmt) {
        case 'formatted':
            $p = $this->data->Offers->Summaries[0]->LowestPrice->DisplayAmount;
            break;
        case 'raw':
        case 'amount':
            $p = $this->data->Offers->Summaries[0]->LowestPrice->Amount;
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
            if (isset($this->data->Offers->Listings[0]->Price->DisplayAmount)) {
                $p = $this->data->Offers->Listings[0]->Price->DisplayAmount;
            }
            break;
        case 'raw':
        case 'amount':
            if (isset($this->data->Offers->Listings[0]->Price->Amount)) {
                $p = $this->data->Offers->Listings[0]->Price->Amount;
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
        return $this->title;
        if (isset($this->data->ItemInfo->Title->DisplayValue)) {
            return $this->data->ItemInfo->Title->DisplayValue;
        } else {
            return '';
        }
    }


    /**
     * Get the Amazon page URL for the item.
     *
     * @return  string      Item URL at Amazon
     */
    public function getAmazonURL()
    {
        if (isset($this->data->DetailPageURL)) {
            return self::stripAWStag($this->data->DetailPageURL);
        } else {
            return '';
        }
    }


    /**
     * Get the URL to the detail page. May be Amazon or Internal.
     *
     * @return  string      Detail page URL
     */
    public function DetailPageUrl()
    {
        global $_CONF_ASTORE;

        if ($_CONF_ASTORE['link_to'] == 'detail') {
            return COM_buildURL(ASTORE_URL . '/detail.php?asin=' . $this->asin);
        } else {
            return $this->getAmazonURL();
        }
    }


    public function getURL()
    {
        return $this->url;
    }


    /**
     * Get the target window for the detail page.
     * Internal pages targe the same window, Amazon targets a new window.
     *
     * @return  string      Page targe, either _self or _blank
     */
    public function DetailPageTarget()
    {
        global $_CONF_ASTORE;

        return $_CONF_ASTORE['link_to'] == 'detail' ? '_self' : '_blank';
    }


    /**
     * Get the URL to the small image for this item.
     *
     * @return  string  Image URL
     */
    public function SmallImage()
    {
        if (!isset($this->data->Images->Primary)) {
            return '';
        }

        $obj = $this->data->Images->Primary;
        if (isset($obj->Small)) {
            return $obj->Small;
        } else {
            return '';
        }
    }


    /**
     * Get the URL to the medium-sized image for this item.
     *
     * @return  string  Image URL
     */
    public function MediumImage()
    {
        if (!isset($this->data->Images->Primary)) {
            return '';
        }

        $obj = $this->data->Images->Primary;
        if (isset($obj->Medium)) {
            return $obj->Medium;
        } elseif (isset($obj->Small)) {
            return $obj->Small;
        } else {
            return '';
        }
    }


    /**
     * Get the URL to the large image for this item.
     *
     * @return  string  Image URL
     */
    public function LargeImage()
    {
        if (!isset($this->data->Images->Primary)) {
            return '';
        }

        $obj = $this->data->Images->Primary;
        if (isset($obj->Large)) {
            return $obj->Large;
        } elseif (isset($obj->Medium)) {
            return $obj->Medium;
        } else {
            return '';
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
        if (isset($this->data->Offers->Listings[0]->DeliveryInfo->IsPrimeEligible)) {
            return $this->data->Offers->Listings[0]->DeliveryInfo->IsPrimeEligible ? 1 : 0;
        } else {
            return 0;
        }
    }


    /**
     * Create the item detail page for display within the site.
     *
     * @return  string      HTML for detail page
     */
    public function detailPage()
    {
        global $_CONF_ASTORE;

        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file('detail', 'detail.thtml');
        $listprice = $this->ListPrice('raw');
        $lowestprice = $this->LowestPrice('raw');
        if (
            ($lowestprice && $listprice && ($lowestprice < $listprice)) ||
            ($lowestprice && !$listprice)
        ) {
            $T->set_var(array(
                'show_lowest' => true,
            ) );
        }
        $T->set_var(array(
            'disclaimer' => $_CONF_ASTORE['full_disclaimer'],
            'item_url'  => $this->getAmazonURL(),
            'title'     => $this->Title(),
            'img_url'   => $this->LargeImage()->URL,
            'img_width' => $this->LargeImage()->Width,
            'img_height' => $this->LargeImage()->Height,
            'listprice' => $this->ListPrice(),
            'lowestprice' => $this->LowestPrice(),
            'long_description' => '',       // not available in APIv5
            'available' => $this->isAvailable(),
            'offers_url' => $this->OffersURL(),
            'is_prime'  => $this->isPrime(),
            'is_admin'  => plugin_ismoderator_astore(),
            'editorial' => $this->editorial,
        ) );
        if ($_CONF_ASTORE['aws_cache_min'] > 0) {
            $T->set_var('asof_date', $this->getDate()->format('d M h:i A T', true));
        }
        $features = $this->Features();
        if (!empty($features)) {
            $T->set_var('has_features', true);
            $T->set_block('detail', 'Features', 'fb');
            foreach ($features as $feature) {
                $T->set_var('feature', $feature);
                $T->parse('fb', 'Features', true);
            }
        }
        $T->parse('output', 'detail');
        $retval = $T->finish($T->get_var('output'));
        return $retval;
    }


    /**
     * Display the products in a grid.
     *
     * @param   array   $items  Array of item objects
     * @return  string      HTML for the product page
     */
    public static function XXshowProducts($items)
    {
        echo __FUNCTION__ . ' not implemented';die;
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
                'url_target' => $item->DetailPageTarget(),
                'lowestprice'   => $item->LowestPrice(),
                //'listprice' => $item->ListPrice(),
                'title'     => COM_truncate($item->Title(),
                        $_CONF_ASTORE['max_blk_desc'], '...'),
                'img_url'   => $item->MediumImage()->URL,
                'img_width' => $item->MediumImage()->Width,
                'img_height' => $item->MediumImage()->Height,
                'formattedprice' => $item->LowestPrice(),
                'displayprice' => $item->DisplayPrice(),
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
    public static function Retrieve(string $asin) : ?object
    {
        global $_CONF_ASTORE;

        // Return from cache if found and not expired
        $data = Cache::get($asin);
        if ($data === NULL) {
            $api = new API;
            $aws = $api->getItems(array($asin));
            foreach ($aws as $asin=>$data) {
                // Automatically add featured items to catalog
                if (isset($data->ItemInfo->Title)) {
                    $title = $data->ItemInfo->Title->DisplayValue;
                } else {
                    $title = '';
                }
                if ($_CONF_ASTORE['auto_add_catalog']) {
                    self::AddToCatalog($asin, $title);
                }
                Cache::set($asin, $data);
            }
        }
        return $data;
    }


    /**
     * Create the item edit form.
     *
     * @return  string      HTML for item edit form
     */
    public function Edit()
    {
        global $_TABLES, $_CONF_ASTORE;

        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file('form', 'edit.thtml');
        $T->set_var(array(
            'asin' => $this->asin,
            'title' => $this->Title(),
            'use_api' => $_CONF_ASTORE['use_api'],
            'ena_chk' => $this->isEnabled() ? 'checked="checked"' : '',
            'url' => $this->url,
            'cat_options' => COM_optionList(
                $_TABLES['astore_categories'],
                'cat_id,cat_name',
                $this->cat_id,
                1
            ),
            'editorial' => $this->editorial,
        ) );
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Add an item to the catalog if not already present.
     *
     * @param   array   $A      Array of item fields
     * @return  boolean         True on success, False on DB error
     */
    public function Save(?array $A=NULL) : bool
    {
        global $_TABLES;

        if (is_array($A)) {
            $this->setVars($A);
        }
        if (empty($this->asin)) {
            return false;
        }

        $db = Database::getInstance();
        $values = array(
            'title' => $this->title,
            'cat_id' => $this->cat_id,
            'url' => $this->url,
            'editorial' => $this->editorial,
            'enabled' => $this->isEnabled(),
            'ts' => time(),
            'asin' => $this->asin,
        );
        $types = array(
            Database::STRING,
            Database::INTEGER,
            Database::STRING,
            Database::STRING,
            Database::INTEGER,
            Database::INTEGER,
            Database::STRING,
        );
        try {
            $db->conn->insert($_TABLES['astore_catalog'], $values, $types);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $k) {
            array_pop($values);     // remove code
            $db->conn->update(
                $_TABLES['astore_catalog'],
                $values,
                array('asin' => $this->asin),
                $types
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }


    /**
     * Get an array of all item objects from the catalog.
     *
     * @param   integer $page       Page number of display
     * @param   array   $cat_ids    Limit to these category IDs
     * @param   boolean $enabled    True to only get enabled items
     * @return  array       Array of item objects
     */
    public static function getAll($page = 1, $cat_ids = array(), $enabled = true)
    {
        global $_TABLES, $_CONF_ASTORE;

        $qb = Database::getInstance()->conn->createQueryBuilder();
        $qb->select('*')
            ->from($_TABLES['astore_catalog']);
        if ($page > 0) {
            $max = (int)$_CONF_ASTORE['perpage'];
            $start = ((int)$page - 1) * $max;
            $qb->setFirstResult($start)
               ->setMaxResults($max);
        }

        $orderby = '';
        switch ($_CONF_ASTORE['sort']) {
        case 'rand':
            $qb->orderBy('RAND()');
            break;
        case 'lifo':
            $qb->orderBy('id', 'DESC');
            break;
        case 'fifo':
            $qb->orderBy('id', 'ASC');
            break;
        case 'none':
        default:
            break;
        }
        if ($enabled) {
            $qb->andWhere('enabled = 1');
        }
        if (!empty($cat_ids)) {
            $qb->AndWhere('cat_id in (:cat_ids)')
                ->setParameter('cat_ids', $cat_ids, Database::PARAM_INT_ARRAY);
        }
        try {
            $stmt = $qb->execute();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        $asins = array();
        if (!$stmt) {
            return $asins;
        }

        if ($_CONF_ASTORE['use_api']) {
            while ($A = $stmt->fetchAssociative()) {
                $allitems[$A['asin']] = new self($A);
                $data = Cache::get($A['asin']);
                if ($data) {
                    $allitems[$A['asin']]->setData($data);
                } else {
                    // Item not in cache, add to list to get from Amazon
                    $asins[] = $A['asin'];
                }
                foreach (self::$required_asins as $asin) {
                    if (!isset($allitems[$asin])) {
                        // Push requested ASINs to the beginning
                        array_unshift($asins, $asin);
                    }
                }
            }
            // Retrieve from Amazon any items not in cache
            if (!empty($asins)) {
                $api = new API;
                $data = $api->getItems($asins);
                $i = 0;
                foreach ($data as $asin=>$info) {
                    unset($asins[$i]);
                    $i++;
                    $info->_timestamp = time();
                    $allitems[$asin] = new self();
                    $allitems[$asin]->setASIN($asin);
                    if (isset($info->ItemAttributes->Title)) {
                        $title = $info->ItemAttributes->Title;
                    } else {
                        $title = '';
                    }
                    $allitems[$asin]->setTitle($title);
                    $allitems[$asin]->setData($info);
                    Cache::set($asin, $info);
                    if ($_CONF_ASTORE['auto_add_catalog']) {
                        // Automatically add to the catalog
                        self::AddToCatalog($asin, $title);
                    }
                }
                // Now, disable any ASIN's that were not found.
                foreach ($asins as $key=>$asin) {
                    self::toggle(1, 'enabled', $asin);
                }
            }
        } else {
            while ($A = $stmt->fetchAssociative()) {
                // Not using the API, just load data from the DB
                $allitems[$A['asin']] = new self($A['asin'], $A);
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

        // Retrieve from Amazon any items not in cache
        if (!empty($asins)) {
            $api = new API;
            $data = $api->getItems($asins);
            foreach ($data as $asin=>$info) {
                $allitems[$asin] = new self();
                $allitems[$asin]->data = $info;
                Cache::set($asin, $info);

                // Items imported by admin are always added to the catalog
                if (isset($info->ItemInfo->Title)) {
                    $title = $info->ItemInfo->Title->DisplayValue;
                } else {
                    $title = '';
                }
                self::AddToCatalog($asin, $title);
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

        $items = array();
        try {
            $stmt = Database::getInstance()->conn->executeQuery(
                "SELECT asin FROM {$_TABLES['astore_catalog']}"
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                $items[] = $A['asin'];
            }
        }
        return implode(',', $items);
    }


    /**
     * Delete an item from the catalog and cache.
     *
     * @param   string  $asin   Item number
     */
    public static function Delete(string $asin) : void
    {
        global $_TABLES;

        try {
            Database::getInstance()->conn->delete(
                $_TABLES['astore_catalog'],
                array('asin' => $asin),
                array(Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
        Cache::delete($asin);
    }


    /**
     * Remove any associate-related tags from the product URL for admins.
     * The entire query string is removed as it is not needed.
     * This is to avoid artifically inflating the click count at Amazon
     * during testing by admins.
     * If the configured header is present, or an admin is logged in and
     * admins should not see associate links, then strip the associate info.
     *
     * @param   string  $url    Product URL
     * @return  string          URL without associate tags
     */
    private static function stripAWStag($url)
    {
        global $_CONF_ASTORE;

        if (
            (
                $_CONF_ASTORE['notag_header'] != '' &&
                isset($_SERVER['HTTP_' . strtoupper($_CONF_ASTORE['notag_header'])])
            ) ||
            $_CONF_ASTORE['notag_admins'] && plugin_ismoderator_astore()
        ) {
            return preg_replace(
                '/(tag|tracking_id)=' . $_CONF_ASTORE['aws_assoc_id'] . '&?/',
                '',
                $url
            );
        } else {
            return $url;
        }
    }


    /**
     * Check if this item is enabled.
     *
     * @return  integer     1 if enabled, 0 if not
     */
    public function isEnabled()
    {
        return $this->enabled ? 1 : 0;
    }


    /**
     * Enable or disable multiple items at once.
     *
     * @param   integer $status     New enabled status, either 1 or 0
     * @param   array|string    $asins  One or more ASIN numbers
     * @return  boolean     True on success, False on error
     */
    public static function bulkEnableDisable(int $status, $asins) : bool
    {
        global $_TABLES;

        if (empty($asins)) {
            return true;
        }
        if (!is_array($asins)) {
            $asins = array($asins);
        }
        $status = $status ? 1 : 0;
        try {
            Database::getInstance()->conn->executeStatement(
                "UPDATE {$_TABLES['astore_catalog']}
                SET enabled = ?
                WHERE asin IN (?)",
                array($status, $asins),
                array(Database::INTEGER, Database::PARAM_STR_ARRAY)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return false;
        }
        Cache::clear();
        return true;
    }


    /**
     * Disable a catalog item when it is unavailable.
     *
     * @uses    self::toggle()
     */
    public function Disable()
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
    public static function toggle(int $oldval, string $field, string $asin) : int
    {
        global $_TABLES;

        $oldval = $oldval == 0 ? 0 : 1;
        $newval = $oldval == 0 ? 1 : 0;

        $db = Database::getInstance();
        $field = $db->conn->quoteIdentifier($field);
        try {
            $db->conn->update(
                $_TABLES['astore_catalog'],
                array($field => $newval),
                array('asin' => $asin),
                array(Database::INTEGER, Database::STRING)
            );
            return $newval;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return $oldval;
        }
    }


    /**
     * Count items contained in a specified category.
     *
     * @param   integer $cat_id     Category record ID
     * @return  integer     Number of items under this category
     */
    public static function countByCategory($cat_id)
    {
        global $_TABLES;

        return (int)Database::getInstance()->getCount(
            $_TABLES['astore_catalog'],
            array('cat_id'),
            array($cat_id),
            array(Database::INTEGER)
        );
    }


    /**
     * Return the value of the `is_valid` flag.
     * Used to check if a valid item was retreived from the database
     * and/or Amazon.
     *
     * @return  integer     1 if item is valid, 0 if not
     */
    public function isValid()
    {
        return $this->is_valid ? 1 : 0;
    }


    /**
     * Get the raw timestamp when data was retrieved.
     *
     * @return  integer     Timestamp value
     */
    public function getTimestamp()
    {
        return (int)$this->data->_timestamp;
    }


    /**
     * Get the cached or retrieved date/time as an object.
     *
     * @return  object      Date object
     */
    public function getDate()
    {
        global $_CONF;
        if (!isset($this->data->_timestamp)) {
            return clone $_CONF['_now'];
        } else {
            return new \Date($this->data->_timestamp, $_CONF['timezone']);
        }
    }


    /**
     * Show the admin list.
     *
     * @param   string  $import_fld     ID of item to import
     * @return  string  HTML for item list
     */
    public static function adminList($import_fld = '')
    {
        global $LANG_ADMIN, $LANG_ASTORE, $LANG01,
            $_TABLES, $_CONF, $_CONF_ASTORE;

        USES_lib_admin();

        $retval = '';
        $form_arr = array();

        $header_arr = array(
            /*array(
                'text' => 'ID',
                'field' => 'id',
                'sort' => true,
            ),*/
            array(
                'text' => 'ASIN',
                'field' => 'asin',
                'sort' => true,
            ),
            array(
                'text' => $LANG01[4],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ASTORE['title'],
                'field' => 'title',
                'sort' => false,
            ),
            array(
                'text' => $LANG_ADMIN['enabled'],
                'field' => 'enabled',
                'sort' => 'false',
                'align' => 'center',
            ),
            array(
                'text' => $LANG_ASTORE['last_update'],
                'field' => 'ts',
                'sort' => 'true',
                'nowrap' => true,
            ),
            array(
                'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
            ),
        );

        $text_arr = array(
            'has_extras' => false,
            'form_url' => ASTORE_ADMIN_URL . '/index.php',
        );
        $ena_action = '<button class="uk-button uk-button-mini uk-button-success" name="enachecked">' .
            '<i class="uk-icon uk-icon-check"></i> ' . $LANG_ADMIN['enable']. '</button>';
        $disa_action = '<button class="uk-button uk-button-mini" name="disachecked">' .
            '<i class="uk-icon uk-icon-remove"></i> ' . $LANG_ADMIN['disable'] . '</button>';
        $del_action = '<button class="uk-button uk-button-mini uk-button-danger" name="delchecked" ' .
            'onclick="return confirm(\'' . $LANG_ASTORE['confirm_del'] . '\');">' .
            '<i class="uk-icon uk-icon-remove"></i> ' . $LANG_ADMIN['delete'] . '</button>';
        $options = array(
            'chkdelete' => 'true',
            'chkfield' => 'asin',
            'chkactions' => $ena_action . '&nbsp;&nbsp;' . $disa_action . '&nbsp;&nbsp;' . $del_action,
        );
        $defsort_arr = array(
            'field' => 'asin',
            'direction' => 'asc',
        );
        $query_arr = array(
            'table' => 'astore_catalog',
            'sql' => "SELECT * FROM {$_TABLES['astore_catalog']}",
        );

        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file('form', 'newitem.thtml');
        $T->set_var(array(
            'import_fld' => $import_fld,
        ) );
        $T->parse('output', 'form');
        $retval .= $T->finish($T->get_var('output'));
        $retval .= ADMIN_list(
            'astore_itemadminlist',
            array(__CLASS__, 'getAdminField'),
            $header_arr,
            $text_arr, $query_arr, $defsort_arr, '', '', $options, $form_arr
        );
        return $retval;
    }


    /**
     * Get the correct display for a single field in the astore admin list.
     *
     * @param   string  $fieldname  Field variable name
     * @param   string  $fieldvalue Value of the current field
     * @param   array   $A          Array of all field names and values
     * @param   array   $icon_arr   Array of system icons
     * @return  string              HTML for field display within the list cell
     */
    public static function getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $_CONF_ASTORE, $LANG_ASTORE;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-edit"></i>',
                ASTORE_ADMIN_URL . '/index.php?edititem=' . $A['asin']
            );
            break;
        case 'delete':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-remove uk-text-danger"></i>',
                ASTORE_ADMIN_URL . "/index.php?delitem={$A['asin']}",
                array(
                     'onclick' => "return confirm('{$LANG_ASTORE['confirm_del']}?');",
                ) );
            break;

        case 'asin':
            if ($_CONF_ASTORE['use_api']) {
                $retval = COM_createLink(
                    $fieldvalue,
                    COM_buildUrl(ASTORE_URL . '/detail.php?asin=' . $fieldvalue)
                );
            } else {
                $retval = $fieldvalue;
            }
            break;

        case 'title':
            if (empty($fieldvalue)) {
                $retval = '<span class="uk-text-danger"><i class="uk-icon uk-icon-exclamation-triangle"></i>&nbsp;Invalid Item</span>';
            } else {
                $retval = $fieldvalue;
            }
            break;

        case 'enabled':
            $chk = $fieldvalue == 1 ? 'checked="checked"' : '';
            $retval = '<input type="checkbox" data-uk-tooltip class="" value="1" ' . $chk .
                "onclick='ASTORE_toggle(this,\"{$A['asin']}\",\"{$fieldname}\");' />" . LB;
            break;

        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }

}
