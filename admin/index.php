<?php
/**
*   Astore admin entry point.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

/** Import core glFusion libraries */
require_once '../../../lib-common.php';

$content = '';

// Must have privileges to access this admin area
if (!plugin_ismoderator_astore()) {
    COM_404();
}

USES_lib_admin();

$action = '';
$actionval = '';
$expected = array(
    'additem', 'delitem',
    'mode', 'view',
);

foreach ($expected as $provided) {
    if (isset($_POST[$provided])) {
        $action = $provided;
        $actionval = $_POST[$provided];
        break;
    } elseif (isset($_GET[$provided])) {
    	$action = $provided;
        $actionval = $_GET[$provided];
        break;
    }
}
// Allow for old-style "mode=xxxx" urls
if ($action == 'mode') {
    $action = $actionval;
}
if ($action == '') $action = 'astores';    // default view
$item = isset($_REQUEST['item']) ? $_REQUEST['item'] : 'astore';
$view = isset($_REQUEST['view']) ? $_REQUEST['view'] : $action;
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';

switch ($action) {
case 'delitem':
    // May also come via GET for a single item
    if (isset($_POST['delitem'])) {
        foreach ($_POST['delitem'] as $item) {
            Astore\Item::Delete($item);
        }
    } elseif (isset($_GET['delitem'])) {
        Astore\Item::Delete($_GET['delitem']);
    }
    $view = 'items';
    break;

case 'additem':
    $asin = isset($_POST['asin']) ? $_POST['asin'] : '';
    if (!empty($asin)) {
        $item = new Astore\Item($asin);
        // Item is already added to the catalog if feat_to_catalog is set
        if (!$_CONF_ASTORE['feat_to_catalog'] && 
                is_object($item) && !$item->isError()) {
            $status = Astore\Item::AddToCatalog($asin);
            if ($status) {
                $msg = sprintf($LANG_ASTORE['add_success'], $asin);
                $level = 'info';
            } else {
                $msg = sprintf($LANG_ASTORE['add_error'], $asin);
                $level = 'error';
            }
            LGLIB_storeMessage(array(
                'message' => sprintf($msg, $asin),
                'level' => $level,
                'pi_code' => $_CONF_ASTORE['pi_name'],
            ) );
        }
    }
    break;

default:
    $view = isset($_REQUEST['view']) ? $_REQUEST['view'] : $action;
    break;
}

// After any action, display the item list
if (isset($_GET['msg'])) {
    $msg = COM_applyFilter($_GET['msg'], true);
    if ($msg > 0) {
        $content .= COM_showMessage($msg, 'astore');
    }
}
$content .= ASTORE_adminItemList();

echo COM_siteHeader('none', $LANG_ASTORE['astores']);
echo ASTORE_adminMenu($view);
echo $content;
echo COM_siteFooter();
exit;

/**
*   Create the administrator menu
*
*   @param  string  $view   View being shown, so set the help text
*   @return string      Administrator menu
*/
function ASTORE_adminMenu($view='')
{
    global $_CONF, $LANG_ADMIN, $LANG_ASTORE, $_CONF_ASTORE;

    $act_items = false;
    $act_categories = false;

    switch ($view) {
    case 'items':
        $act_items = true;
        break;

    case 'categories':
        $act_categories = true;
        $new_menu = array(
            'url'  => ASTORE_ADMIN_URL . '/index.php?edit=x&item=category',
            'text' => '<span class="banrNewAdminItem">' .
                    $LANG_ASTORE['new_cat'] . '</span>',
        );
        break;
    }

    $menu_arr = array(
        array(
            'url'  => ASTORE_ADMIN_URL . '/index.php',
            'text' => $LANG_ASTORE['items'],
            'active' => $act_items,
        ),
        /*array(
            'url'  => ASTORE_ADMIN_URL . '/index.php?categories=x',
            'text' => $LANG_ASTORE['categories'],
            'active' => $act_categories,
        ),*/
        array(
            'url'  => $_CONF['site_admin_url'],
            'text' => $LANG_ADMIN['admin_home'],
        ),
        $new_menu,
    );

    $T = new \Template(ASTORE_PI_PATH . '/templates');
    $T->set_file('title', 'admin.thtml');
    $T->set_var(array(
        'version'   => $_CONF_ASTORE['pi_version'],
    ) );
    $retval = $T->parse('', 'title');
    $retval .= ADMIN_createMenu($menu_arr, $hdr_txt,
            plugin_geticon_astore());
    return $retval;
}


/**
*   Show the admin list
*
*   @return string  HTML for item list
*/
function ASTORE_adminItemList()
{
    global $LANG_ADMIN, $LANG_ASTORE,
            $_TABLES, $_CONF, $_CONF_ASTORE;

    USES_lib_admin();

    $retval = '';
    $form_arr = array();

    $header_arr = array(
        array(  'text' => 'ASIN',
                'field' => 'asin',
                'sort' => true),
        array(  'text' => $LANG_ASTORE['title'],
                'field' => 'title',
                'sort' => false),
        array(  'text' => $LANG_ADMIN['delete'],
                'field' => 'delete',
                'sort' => false,
                'align' => 'center'),
    );

    $text_arr = array(
        'has_extras' => false,
        'form_url' => ASTORE_ADMIN_URL . '/index.php',
    );

    $options = array('chkdelete' => 'true', 'chkfield' => 'asin');
    $defsort_arr = array('field' => 'asin', 'direction' => 'asc');
    $query_arr = array(
        'table' => 'astore_catalog',
        'sql' => "SELECT cat.asin AS asin, cache.data as title
                FROM {$_TABLES['astore_catalog']} AS cat
                LEFT JOIN {$_TABLES['astore_cache']} AS cache
                    ON cat.asin = cache.asin",
    );

    $T = new Template(ASTORE_PI_PATH . '/templates');
    $T->set_file('form', 'newitem.thtml');
    $T->parse('output', 'form');
    $retval .= $T->finish($T->get_var('output'));
    $retval .= ADMIN_list('astore', 'ASTORE_getAdminField', $header_arr,
                $text_arr, $query_arr, $defsort_arr, '', '', $options, $form_arr);
    return $retval;
}


/**
*   Get the correct display for a single field in the astore admin list
*
*   @param  string  $fieldname  Field variable name
*   @param  string  $fieldvalue Value of the current field
*   @param  array   $A          Array of all field names and values
*   @param  array   $icon_arr   Array of system icons
*   @return string              HTML for field display within the list cell
*/
function ASTORE_getAdminField($fieldname, $fieldvalue, $A, $icon_arr)
{
    global $_CONF, $LANG_ACCESS, $_CONF_ASTORE;

    $retval = '';

    switch($fieldname) {
    case 'asin':
        $retval = COM_createLink($fieldvalue, ASTORE_URL . '/index.php?mode=detail&asin=' . $fieldvalue);
        break;

    case 'delete':
        $retval = COM_createLink('<i class="' . ASTORE_getIcon('trash', 'danger') . '"></i>',
                ASTORE_ADMIN_URL . "/index.php?delitem={$A['asin']}",
                array(
                     'onclick' => "return confirm('Do you really want to delete this item?');",
                ) );
        break;

    case 'title':
        $X = new \SimpleXMLElement($fieldvalue);
        $title = $X->ItemAttributes->Title;
        if ($title !== NULL)  {
            $retval = $title->__toString();
        } else {
            $retval = '';
        }
        break;

    default:
        $retval = $fieldvalue;
        break;
    }
    return $retval;
}

?>
