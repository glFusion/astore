<?php
/**
 * Create an amazon store catalog.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore;


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


    public function setASIN($asin)
    {
        $this->asin = $asin;
        return $this;
    }

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


    public function getPage($page=1, $orderby='id')
    {
        global $_TABLES;

        $retval = array();
        $perpage = 25;      // todo - config item

        switch ($orderby) {
        case 'id':
            $ord = "`id` ASC";
            break;
        case 'ts':
            $ord = '`ts` DESC';
            break;
        case 'rand':
            $ord = 'RAND()';
            break;
        }
        if ($this->Featured) {
            $exclude = "AND asin <> '{$this->Featured->getASIN()}'";
        } else {
            $exclude = '';
        }
        $start = ($page - 1) * $perpage;
        $sql = "SELECT * FROM {$_TABLES['astore_catalog']}
            WHERE enabled=1 $exclude
            ORDER BY $ord
            LIMIT $start, $perpage";
        $res = DB_query($sql);
        if ($res) {
            while ($A = DB_fetchArray($res, false)) {
                $retval[] = new Item($A['asin']);
            }
        }
        return $retval;
    }


    public function renderSearch($query, $page=1)
    {
        $Item = new Item;
        $API = new API;
        $this->query = $query;
        $this->Items = $API->searchItems($query, $page);
        return $this->Render($page);
    }

    public function renderItems($page=1)
    {
        $this->Items = Item::getAll($page, $this->cat_ids);
        return $this->Render($page);
    }


    /**
     * Display the products in a grid.
     *
     * @param   array   $items  Array of item objects
     * @return  string      HTML for the product page
     */
    public function Render($page=1)
    {
        global $_CONF_ASTORE;

        $isAdmin = plugin_ismoderator_astore();
        $hasQuery = $this->query != '';

        if ($page == 1) {
            $Item = $this->getFeatured();
        }

        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file(array(
            'store' => 'store.thtml',
        ) );
        $T->set_var(array(
            'query'         => $this->query,
            'store_title'   => $_CONF_ASTORE['store_title'],
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
        }
        //$Items = Item::getAll($page, $this->cat_ids);
        $Items = $this->Items;
        $T->set_block('store', 'products', 'pb');
        foreach ($Items as $Item) {
            /*if (!$Item->isAvailable()) {
                $Item->Disable();
                continue;
        }*/

            $T->set_var(array(
                'item_id' => $Item->ASIN(),
                'item_url'  => $Item->DetailPageURL(),
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
            $T->parse('pb', 'products', true);
        }

        if (!$hasQuery) {
            // Display pagination, only if not searching Amazon
            $count = self::Count();
            $pagenav_args = '';
            if (
                isset($_CONF_ASTORE['perpage']) &&
                $_CONF_ASTORE['perpage'] > 0 &&
                $count > $_CONF_ASTORE['perpage']
            ) {
                $T->set_var(
                    'pagination',
                    COM_printPageNavigation(
                        ASTORE_URL . '/index.php' . $pagenav_args,
                        $page,
                        ceil($count / $_CONF_ASTORE['perpage'])
                    )
                );
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
     * @param   string  $asin   Item number
     */
    public static function Delete($id)
    {
        global $_TABLES;

        $id = (int)$id;
        DB_delete($_TABLES['astore_catalog'], 'id', $id);
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
    public static function toggle($oldval, $field, $id)
    {
        global $_TABLES;

        $oldval = $oldval == 0 ? 0 : 1;
        $newval = $oldval == 0 ? 1 : 0;
        $field = DB_escapeString($field);
        $asin = DB_escapeString($asin);
        $sql = "UPDATE {$_TABLES['astore_catalog']}
            SET $field = $newval
            WHERE id = $id";
        DB_query($sql);
        if (DB_error()) {
            return $oldval;
        } else {
            return $newval;
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
     * Show the admin list.
     *
     * @param   string  $import_fld     ID of item to import
     * @return  string  HTML for item list
     */
    function adminList($import_fld = '')
    {
        global $LANG_ADMIN, $LANG_ASTORE, $LANG01,
            $_TABLES, $_CONF, $_CONF_ASTORE;

        USES_lib_admin();

        $retval = '';
        $form_arr = array();

        $header_arr = array(
            array(
                'text' => 'ID',
                'field' => 'id',
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

        $options = array(
            'chkdelete' => 'true',
            'chkfield' => 'id',
        );
        $defsort_arr = array(
            'field' => 'id',
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


    public function Edit()
    {
        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file('form', 'edit.thtml');
        $T->set_var(array(
            'id' => $this->id,
            'asin' => $this->asin,
            'title' => $this->title,
            'ena_chk' => $this->isEnabled() ? 'checked="checked"' : '',
            'url' => $this->url,
        ) );
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
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

?>
