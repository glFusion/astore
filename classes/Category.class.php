<?php
/**
 * Class for managing item categories
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
 * Class for category objects.
 */
class Category
{
    /** Category record ID.
     * @var integer */
    private $cat_id = 0;

    /** Category short name.
     * @var string */
    private $cat_name = '';

    /** Display name.
     * @var string */
    private $disp_name = '';

    /** Display order.
     * @var integer */
    private $orderby = 0;


    /**
     * Constructor - Load default values and read a record.
     *
     * @param   array|integer   $data   Category record or ID
     */
    public function __construct($data = 0)
    {
        global $_CONF_ASTORE;

        $catid = (int)$catid;
        if (is_array($data)) {
            $this->setVars($data, true);
        } else {
            $this->cat_id = (int)$data;
            $this->Read();
        }
    }


    /**
     * Get a category instance. Caches objects in an array.
     *
     * @param   integer $cat_id     Category record ID
     * @return  object      Category object
     */
    public static function getInstance($cat_id)
    {
        static $Cats = array();

        if (is_array($cat_id)) {
            $id = (int)$cat_id['cat_id'];
        } else {
            $id = (int)$cat_id;
        }
        if (!isset($Cats[$id])) {
            $Cats[$id] = new self($cat_id);
        }
        return $Cats[$id];
    }


    public static function getAll()
    {
        global $_TABLES;

        $retval = array();
        $sql = "SELECT * FROM {$_TABLES['astore_categories']}
            ORDER BY orderby ASC";
        $res = DB_query($sql);
        while ($A = DB_fetchArray($res, false)) {
            $retval[$A['cat_id']] = new self($A);
        }
        return $retval;
    }


    /**
     * Sets all variables to the matching values from the provided array.
     *
     * @param   array   $A      Array of values, from DB or $_POST
     * @param   boolean $fromDB True if reading a DB record, False for $_POST
     */
    public function setVars($A, $fromDB = false)
    {
        if (!is_array($A)) return;

        $this->cat_id   = (int)$A['cat_id'];
        $this->cat_name     = $A['cat_name'];
        $this->disp_name = isset($A['disp_name']) ? $A['disp_name'] : $A['cat_name'];
        $this->orderby  = (int)$A['orderby'];
    }


    /**
     * Read one record from the database.
     *
     * @param   integer $id     Optional ID.  Current ID is used if zero
     * @return  boolean         True on success, False on failure
     */
    public function Read($id = 0)
    {
        global $_TABLES;

        if ($id != 0) {
            $this->cat_id = $id;
        }
        if ($this->cat_id == 0) return false;

        $result = DB_query(
            "SELECT * FROM {$_TABLES['astore_categories']}
            WHERE cat_id={$this->cat_id}
            LIMIT 1"
        );
        $A = DB_fetchArray($result, false);
        $this->setVars($A, true);
        return true;
    }


    /**
     * Save a new or updated category.
     *
     * @param   array   $A      Optional array of new values
     * @return  string      Error message, empty string on success
     */
    public function Save($A = array())
    {
        global $_TABLES, $_CONF_ASTORE;

        if (!empty($A)) {
            $this->setVars($A);
        }

        if ($this->isNew()) {
            $sql1 = "INSERT INTO {$_TABLES['astore_categories']} SET ";
            $sql3 = '';
        } else {
            $sql1 = "UPDATE {$_TABLES['astore_categories']} SET ";
            $sql3 = " WHERE cat_id = {$this->cat_id}";
        }

        $sql2 = "cat_name = '" . DB_escapeString($this->cat_name) . "',
            orderby = " . (int)$this->orderby;
        $sql = $sql1 . $sql2 . $sql3;
        //echo $sql;die;
        $result = DB_query($sql);
        if (!$result) {
            return false;
        } else {
            self::reOrder();
            return true;
        }
    }


    /**
     * Reorder all records.
     */
    private static function reOrder()
    {
        global $_TABLES;

        $sql = "SELECT cat_id, orderby
                FROM {$_TABLES['astore_categories']}
                ORDER BY orderby ASC;";
        $result = DB_query($sql);

        $order = 10;
        $stepNumber = 10;
        while ($A = DB_fetchArray($result, false)) {
            if ($A['orderby'] != $order) {  // only update incorrect ones
                $sql = "UPDATE {$_TABLES['astore_categories']}
                    SET orderby = '$order'
                    WHERE cat_id = {$A['cat_id']}";
                DB_query($sql);
            }
            $order += $stepNumber;
        }
    }


    /**
     * Move a record up or down the admin list.
     *
     * @param   string  $id     ID field value
     * @param   string  $where  Direction to move (up or down)
     */
    public static function moveRow($id, $where)
    {
        global $_TABLES;

        switch ($where) {
        case 'up':
            $oper = '-';
            break;
        case 'down':
            $oper = '+';
            break;
        default:
            $oper = '';
            break;
        }
        $id = (int)$id;
        if (!empty($oper)) {
            $sql = "UPDATE {$_TABLES['astore_categories']}
                    SET orderby = orderby $oper 11
                    WHERE cat_id = $id";
            //echo $sql;die;
            DB_query($sql);
            self::ReOrder();
        }
    }


    /**
     * Deletes all checked categories.
     * Calls catDelete() to perform the actual deletion
     *
     * @param   array   $var    Form variable containing array of IDs
     * @return  string  Error message, if any
     */
    public static function DeleteMulti($var)
    {
        $display = '';

        foreach ($var as $catid) {
            if (!self::Delete($catid)) {
                $display .= "Error deleting category {$catid}<br />";
            }
        }
        return $display;
    }


    /**
     * Delete a category, and all sub-categories, and all ads.
     *
     * @param   integer  $id     Category ID to delete
     * @return  boolean          True on success, False on failure
     */
    public static function Delete($id)
    {
        global $_TABLES, $_CONF_ASTORE;

        $id = (int)$id;
        if ($id == 1) return false;     // can't delete root category
        $sql = "UPDATE {$_TABLES['astore_catalog']}
            SET cat_id = 1 WHERE cat_id = $id";
        DB_query($sql);
        DB_delete($_TABLES['astore_categories'], 'cat_id', $id);
        return true;
    }


    /**
     * Create an edit form for a category.
     *
     * @param   integer $cat_id Category ID, zero for a new entry
     * @return  string      HTML for edit form
     */
    public function Edit()
    {
        $cat_id = (int)$cat_id;
        if ($cat_id > 0) {
            // Load the requested category
            $this->cat_id = $cat_id;
            $this->Read();
        }
        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file('form', 'catform.thtml');
        $T->set_var(array(
            'cat_name'  => $this->cat_name,
            'cat_id'    => $this->cat_id,
            'orderby_opts' => self::buildSelection($this->orderby - 10 ,$this->orderby),
            'orderby_last'  => $this->isNew() ? 'selected="selected"' : '',
        ) );
        $T->parse('output', 'form');
        return $T->finish($T->get_var('output'));
    }


    /**
     * Recurse through the category table building an option list sorted by id.
     *
     * @param integer  $sel     Category ID to be selected in list
     * @param integer  $self    Current category ID
     * @return string           HTML option list, without <select> tags
     */
    public static function buildSelection($sel=0, $self=0)
    {
        global $_TABLES;

        return COM_optionList(
            $_TABLES['astore_categories'],
            'orderby,cat_name',
            $sel,
            1
        );
    }


    /**
     * Get the record ID for this category.
     *
     * @return  integer     Category ID
     */
    public function getID()
    {
        return (int)$this->cat_id;
    }


    /**
     * Get the category name
     *
     * @return  string      Category name
     */
    public function getName()
    {
        return $this->cat_name;
    }


    /**
     * Create an admin list of categories.  Currently Unused.
     *
     * @return  string  HTML for admin list of categories
     */
    public static function adminList()
    {
        global $_TABLES, $_CONF_ASTORE, $LANG_ASTORE;

        USES_lib_admin();
        $header_arr = array(
            array(
                'text' => $LANG_ASTORE['edit'],
                'field' => 'edit',
                'sort' => false,
                'align' => 'center',
            ),
            array(
                'text' => 'ID',
                'field' => 'cat_id',
                'sort' => false,
            ),
            array(
                'text' => $LANG_ASTORE['name'],
                'field' => 'cat_name',
                'sort' => false,
            ),
            array(
                'text' => $LANG_ASTORE['order'],
                'field' => 'orderby',
                'sort' => false,
            ),
            array(
                'text' => $LANG_ASTORE['delete'],
                'field' => 'delete',
                'sort' => false,
                'align' => 'center',
            ),
        );
        $defsort_arr = array(
            'field' => 'orderby',
            'direction' => 'ASC',
        );
        $text_arr = array(
            'has_extras' => true,
            'form_url' => $_CONF_ASTORE['admin_url'] . '/index.php?categories',
        );
        $query_arr = array(
            'table' => 'astore_categories',
            'sql' => "SELECT * FROM {$_TABLES['astore_categories']}",
            'query_fields' => array(),
            'default_filter' => ''
        );
        $form_arr = array();
        $retval = COM_createLink(
            $LANG_ASTORE['mnu_newcat'],
            ASTORE_ADMIN_URL . '/index.php?editcat=x',
            array(
                'class' => 'uk-button uk-button-success',
                'style' => 'float:left',
            )
        );
        $extra = array(
            'cat_count' => DB_count($_TABLES['astore_categories']),
        );
        $retval .= ADMIN_list(
            'astore_admincatlist',
            array(__CLASS__, 'getListField'),
            $header_arr, $text_arr, $query_arr, $defsort_arr,
            '', $extra, '', $form_arr
        );
        return $retval;
    }


    /**
     * Display field contents for the Category admin list.
     *
     * @param   string  $fieldname  Name of the field
     * @param   string  $fieldvalue Value to be displayed
     * @param   array   $A          Associative array of all values available
     * @param   array   $icon_arr   Array of icons available for display
     * @return  string              Complete HTML to display the field
     */
    public static function getListField($fieldname, $fieldvalue, $A, $icon_arr, $extra)
    {
        global $_CONF_ASTORE, $LANG_ASTORE, $_TABLES;

        $retval = '';

        switch($fieldname) {
        case 'edit':
            $retval = COM_createLink('',
                ASTORE_ADMIN_URL . "/index.php?editcat={$A['cat_id']}",
                array(
                    'class' => 'uk-icon uk-icon-edit',
                )
            );
            break;

        case 'delete':
            if ($A['cat_id'] > 1) {
                $conf_txt = $LANG_ASTORE['confirm_delitem'] . ' ' .
                    $LANG_ASTORE['confirm_delcat'];
                $retval .= COM_createLink('',
                    $_CONF_ASTORE['admin_url'] .
                        "/index.php?deletecat=cat&amp;cat_id={$A['cat_id']}",
                    array(
                        'title' => $LANG_ASTORE['del_item'],
                        'class' => 'uk-icon uk-icon-remove uk-text-danger',
                        'data-uk-tooltip' => '',
                        'onclick' => "return confirm('{$conf_txt}');",
                    )
                );
            }
            break;

        case 'orderby':
            if ($fieldvalue > 10) {
                $retval = COM_createLink(
                    '<i class="uk-icon uk-icon-arrow-up"></i>',
                    ASTORE_ADMIN_URL . '/index.php?movecat=up&cat_id=' . $A['cat_id']
                );
            } else {
                $retval = '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            if ($fieldvalue < $extra['cat_count'] * 10) {
                $retval .= COM_createLink(
                    '<i class="uk-icon uk-icon-arrow-down"></i>',
                    ASTORE_ADMIN_URL . '/index.php?movecat=down&cat_id=' . $A['cat_id']
                );
            } else {
                $retval .= '<i class="uk-icon uk-icon-justify">&nbsp;</i>';
            }
            break;


        default:
            $retval = $fieldvalue;
            break;
        }
        return $retval;
    }


    /**
     * Check if this is a new record.
     *
     * @return  integer     1 if new, False if existing
     */
    public function isNew()
    {
        return $this->cat_id == 0 ? 1 : 0;
    }

}

?>
