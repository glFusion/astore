<?php
/**
 * Create an amazon store catalog.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020-2023 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.3
 * @since       v0.2.0
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
class Catalog
{
    /** Cache tag applied to all cached items from this plugin.
     * @var string */
    protected static $tag = 'astore_cat';

    /** Featured item.
     * @var string */
    private $Featured = NULL;

    /** Items to be shown.
     * @var array */
    private $Items = array();

    /** Featured ASIN.
     * @var string */
    private $asin = '';

    /** Category IDs to limit display.
     * @var array */
    private $cat_ids = array();

    /** Query string to pre-populate the query field.
     * @var string */
    private $query = '';


    /**
     * Set the ASIN value.
     *
     * @param   string  $asin   ASIN value
     * @return  object  $this
     */
    public function setASIN($asin)
    {
        $this->asin = $asin;
        return $this;
    }


    /**
     * Set the featured item. Relies on `asin` being set.
     *
     * @return  object  Featured Item object
     */
    public function getFeatured()
    {
        global $_TABLES;

        if ($this->asin) {
            $this->Featured = new Item($this->asin);
            return $this->Featured;
        } else {
            return NULL;
        }
    }


    /**
     * Get a page of items from the database.
     *
     * @param   integer $page       Page number to retrieve
     * @param   string  $orderby    Optional field to order results
     * @return  array       Array of Item objects
     */
    public function getPage($page=1, $orderby='id')
    {
        global $_TABLES;

        $retval = array();
        $perpage = 25;      // todo - config item
        if ($page < 1) {
            $page = 1;
        }

        $qb = Database::getInstance()->conn->createQueryBuilder();
        $qb->select('*')
           ->from($_TABLES['astore_catalog'])
           ->where('enabled=1')
           ->setFirstResult(($page - 1) * $perpage)
           ->setMaxResults($perpage);
        switch ($orderby) {
        case 'id':
            $qb->orderBy('id', 'ASC');
            break;
        case 'ts':
            $qb->orderBy('ts', 'DESC');
            break;
        case 'rand':
            $qb->orderBy('RAND()');
            break;
        }
        if ($this->Featured) {
            $qb->andWhere('asin <> :feat_asin')
               ->setParameter('feat_asin', $this->Featured->getASIN(), Database::STRING);
        }
        try {
            $stmt = $qb->execute();
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            $stmt = false;
        }
        if ($stmt) {
            while ($A = $stmt->fetchAssociative()) {
                $retval[] = new Item($A['asin']);
            }
        }
        return $retval;
    }


    /**
     * Render the search results.
     *
     * @param   string  $query  Search query
     * @param   integer $page   Page number to show
     * @return  string      HTML for the item list
     */
    public function renderSearch($query, $page=1)
    {
        global $_CONF_ASTORE;

        $API = new API;
        $this->query = $query;
        $this->Items = $API->searchItems($query, $page);
        /*if (!empty($this->Items) && $_CONF_ASTORE['auto_add_catalog']) {
            foreach ($this->Items as $asin=>$Item) {
                Item::AddToCatalog($asin, $Item->Title());
            }
        }*/
        return $this->Render($page);
    }



    /**
     * Render the item list view, no search.
     *
     * @param   integer $page   Page number to show
     * @return  string      HTML for the item list
     */
    public function renderItems($page=1)
    {
        if ($this->Pages() < $page) {
            $page = 1;
        }
        $this->Items = Item::getAll($page, $this->cat_ids);
        return $this->Render($page);
    }


    /**
     * Display the products in a grid.
     *
     * @param   integer $page   Page number to show
     * @return  string      HTML for the product page
     */
    public function Render($page=1)
    {
        global $_CONF_ASTORE, $_CONF;

        $isAdmin = plugin_ismoderator_astore();
        $hasQuery = $this->query != '';

        if ($page == 1) {
            $Item = $this->getFeatured();
        } else {
            $Item = NULL;
        }

        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file(array(
            'store' => $_CONF_ASTORE['use_api'] ? 'store.thtml' : 'store_noapi.thtml',
        ) );
        $T->set_var(array(
            'query'         => $this->query,
            'store_title'   => $_CONF_ASTORE['store_title'],
            'disclaimer'    => $_CONF_ASTORE['full_disclaimer'],
            'page'          => $page,
            'can_search'    => $this->canSearch(),
        ) );

        $T->set_block('catlist', 'CatChecks', 'CC');
        $i = 0;
        foreach (Category::getAll() as $Cat) {
            if (Item::countByCategory($Cat->getID()) < 1) {
                continue;
            }
            $T->set_var(array(
                'cat_id'    => $Cat->getID(),
                'cat_name'  => $Cat->getName(),
                'cat_chk'   => in_array($Cat->getID(), $this->cat_ids) ? 'checked="checked"' : '',
                'cnt'       => ++$i,
            ) );
            $T->parse('CC', 'CatChecks', true);
        }

        if ($Item) {
            if ($_CONF_ASTORE['use_api']) {
                $T->set_var(array(
                    'featured' => true,
                    'f_item_id' => $Item->ASIN(),
                    'f_item_url'  => $Item->DetailPageURL(),
                    'f_lowestprice' => $Item->LowestPrice(),
                    'f_listprice' => $Item->ListPrice(),
                    'f_title'     => $Item->Title(),
                    'f_img_url'   => $Item->MediumImage()->URL,
                    'f_img_width' => $Item->MediumImage()->Width,
                    'f_img_height' => $Item->MediumImage()->Height,
                    'f_formattedprice' => $Item->ListPrice(),
                    'f_long_description' => '',     // not available in APIv5
                    'f_offers_url' => $Item->OffersURL(),
                    'f_available' => $Item->isAvailable(),
                    'f_is_prime' => $Item->isPrime() ? true : false,
                    'f_is_admin' => $isAdmin,
                ) );
            } else {
                    $T->set_var('item_data', $Item->getURL());
            }
        }
        //$Items = Item::getAll($page, $this->cat_ids);
        $Items = $this->Items;
        $T->set_block('store', 'products', 'pb');
        foreach ($Items as $Item) {
            if ($_CONF_ASTORE['use_api']) {
                if (!$Item->isAvailable()) {
                    $Item->Disable();
                    continue;
                }
                try {
                    $T->set_var(array(
                        'item_id' => $Item->ASIN(),
                        'item_url'  => $Item->DetailPageURL(),
                        'url_target' => $Item->DetailPageTarget(),
                        'lowestprice'   => $Item->LowestPrice(),
                        'listprice' => $Item->ListPrice(),
                        'title'     => COM_truncate($Item->Title(),
                            $_CONF_ASTORE['max_blk_desc'], '...'),
                        'img_url'   => $Item->MediumImage()->URL,
                        'img_width' => $Item->MediumImage()->Width,
                        'img_height' => $Item->MediumImage()->Height,
                        'formattedprice' => $Item->ListPrice(),
                        'displayprice' => $Item->DisplayPrice(),
                        'long_description' => '',
                        'offers_url' => $Item->OffersURL(),
                        'available' => $Item->isAvailable(),
                        'is_prime' => $Item->isPrime() ? true : false,
                        'is_admin' => $isAdmin,
                    ) );
                    if ($_CONF_ASTORE['aws_cache_min'] > 0) {
                        $T->set_var('asof_date', $Item->getDate()->format('h:i A T', true));
                    }
                } catch (\Exception $e) {
                    // Just skip it
                }
            } else {
                $url = $Item->getURL();
                if (empty($url)) {
                    continue;
                }
                $T->set_var('item_data', $url);
            }
            $T->parse('pb', 'products', true);
        }

        if (!$hasQuery) {
            // Display pagination, only if not searching Amazon
            $count = $this->Count();
            $pagenav_args = '';
            if (!empty($this->cat_ids)) {
                $pagenav_args = '?' . http_build_query(array('cats'=>$this->cat_ids));
            }
            if ($this->Pages() > 1) {
                $T->set_var(
                    'pagination',
                    COM_printPageNavigation(
                        ASTORE_URL . '/index.php' . $pagenav_args,
                        $page,
                        ceil($count / $_CONF_ASTORE['perpage'])
                    )
                );
            } else {
                $page = 1;
            }
        }
        $T->parse('output', 'store');
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
     * Delete an item from the catalog and cache.
     *
     * @param   integer $id     Item record ID
     */
    public static function Delete(int $id) : void
    {
        global $_TABLES;

        try {
            Database::getInstance()->conn->delete(
                $_TABLES['astore_catalog'],
                array('id'),
                array($id),
                array(Database::STRING)
            );
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
        }
    }


    /**
     * Get the number of items in the catalog.
     * Used for pagination.
     *
     * @return  integer     Count of items in the catalog table
     */
    public function Count()
    {
        global $_TABLES;
        static $count = NULL;      // keep throughout the current page load

        if ($count === NULL) {
            $count = 0;
            $sql = "SELECT count(*) AS cnt
                FROM {$_TABLES['astore_catalog']}
                WHERE enabled = 1";
            if (!empty($this->cat_ids)) {
                $sql .= ' AND cat_id IN (' . implode(',', $this->cat_ids) . ')';
            }
            try {
                $A = Database::getInstance()->conn->executeQuery($sql)->fetchAssociative();
            } catch (\Throwable $e) {
                Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
                $A = false;
            }
            if (is_array($A)) {
                $count = (int)$A['cnt'];
            } else {
                $count = 0;
            }
        }
        return $count;
    }


    /**
     * Get the number of pages.
     *
     * @return  integer     Number of pages
     */
    public function Pages()
    {
        global $_CONF_ASTORE;

        $count = $this->Count();

        if (!isset($_CONF_ASTORE['perpage']) ||
            $_CONF_ASTORE['perpage'] < 1) {
            $_CONF_ASTORE['perpage'] = 10;
        }
        return ceil($count / $_CONF_ASTORE['perpage']);
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
    private static function XXstripAWStag($url)
    {
        echo __FUNCTION__ . ' not implemented';die;
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
     * @param   string  $id         Item ID
     * @return  integer     New value, or old value in case of error
     */
    public static function toggle(int $oldval, string $field, string $id) : int
    {
        global $_TABLES;

        $oldval = $oldval == 0 ? 0 : 1;
        $newval = $oldval == 0 ? 1 : 0;
        try {
            Database::getInstance()->conn->update(
                $_TABLES['astore_catalog'],
                array($field => $newval),
                array('id' => $id),
                array(Database::INTEGER, Database::STRING)
            );
            return $newval;
        } catch (\Throwable $e) {
            Log::write('system', Log::ERROR, __METHOD__ . ': ' . $e->getMessage());
            return $oldval;
        }
    }


    /**
     * Set the category ID limiters.
     * May be called multiple times.
     *
     * @param   array   $cats   Array of category IDs
     * @return  object  $this
     */
    public function addCats($cats=array())
    {
        if (is_array($cats)) {
            foreach ($cats as $id) {
                $this->cat_ids[] = (int)$id;
            }
        } elseif ((int)$cats > 0) {
            $this->cat_ids[] = (int)$cats;
        }
        return $this;
    }


    /**
     * Check if the current user is allowed to search the catalog.
     *
     * @return  boolean     True if search is allowed, False if not
     */
    private function canSearch()
    {
        global $_GROUPS, $_CONF_ASTORE;

        return $_CONF_ASTORE['use_api'] &&
            in_array($_CONF_ASTORE['grp_search'], $_GROUPS);
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
    public static function XgetAdminField($fieldname, $fieldvalue, $A, $icon_arr)
    {
        global $_CONF, $LANG_ACCESS, $_CONF_ASTORE;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-edit"></i>',
                ASTORE_ADMIN_URL . '/index.php?edit=' . $A['id']
            );
            break;
        case 'delete':
            $retval = COM_createLink(
                '<i class="uk-icon uk-icon-remove uk-text-danger"></i>',
                ASTORE_ADMIN_URL . "/index.php?delitem={$A['asin']}",
                array(
                     'onclick' => "return confirm('Do you really want to delete this item?');",
                ) );
            break;

        case 'title':
            if (empty($fieldvalue)) {
                $retval = '<i class="uk-icon uk-icon-exclamation-triangle ast-icon-danger"></i>&nbsp;<span class="ast-icon-danger">Invalid Item</span>';
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
