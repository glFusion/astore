<?php
/**
 * Astore admin entry point.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2020 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

/** Import core glFusion libraries */
require_once '../../../lib-common.php';

$content = '';

// Must have privileges to access this admin area
if (!plugin_ismoderator_astore()) {
    COM_404();
}

USES_lib_admin();

$Request = new Astore\Models\Request;
$expected = array(
    'saveitem', 'importcsv', 'exportcsv', 'clearcache',
    'savecat', 'delcat', 'movecat',
    'enachecked', 'disachecked', 'delchecked',
    'delitem',      // must follow the above checkbox-acton values
    'import',
    'edititem', 'editcat', 'categories', 'mode', 'view',
);
list($action, $actionval) = $Request->getAction($expected, 'items');

// Allow for old-style "mode=xxxx" urls
if ($action == 'mode') {
    $action = $actionval;
}
$view = $Request->getString('view', $action);
$import_fld = '';

switch ($action) {
case 'exportcsv':
    $items = Astore\Item::exportItems();
    if (!empty($items)) {
        $content .= '<textarea style="width:100%" class="tooltip" title="' .
            $LANG_ASTORE['instr_export'] . '">' . $items . '</textarea>';
    }
    $content .= Astore\Item::adminList($import_fld);
    break;

case 'importcsv':
    $csv = isset($_POST['asins']) ? $_POST['asins'] : '';
    if (!empty($csv)) {
        $import_fld = Astore\Item::importItems($csv, true);
        if (!empty($import_fld)) {
            $content .= COM_showMessageText($LANG_ASTORE['err_adm_import_size']);
        }
    }
    $content .= Astore\Item::adminList($import_fld);
    break;

case 'saveitem':
    $status = Astore\Item::getInstance($_POST['asin'])->Save($_POST);
    COM_refresh(ASTORE_ADMIN_URL . '/index.php');
    break;

case 'enachecked':
    if (is_array($_POST['delitem'])) {
        Astore\Item::bulkEnableDisable(1, $_POST['delitem']);
    }
    COM_refresh(ASTORE_ADMIN_URL . '/index.php');
    break;

case 'disachecked':
    if (is_array($_POST['delitem'])) {
        Astore\Item::bulkEnableDisable(0, $_POST['delitem']);
    }
    COM_refresh(ASTORE_ADMIN_URL . '/index.php');
    break;

case 'delchecked':
case 'delitem':
    $items = $Request->get('delitem');
    if (is_array($items)) {
        foreach ($items as $item) {
            Astore\Item::Delete($item);
        }
    } else {
        // Deleting a single item
        Astore\Item::Delete($items);
    }
    COM_refresh(ASTORE_ADMIN_URL . '/index.php');
    break;

case 'clearcache':
    Astore\Cache::clear();
    COM_refresh(ASTORE_ADMIN_URL . '/index.php');
    break;

case 'savecat':
    if (Astore\Category::getInstance($_POST['cat_id'])->Save($_POST)) {
        COM_setMsg("Saved successfully");
    } else {
        COM_setMsg("Save failed");
    }
    COM_refresh(ASTORE_ADMIN_URL . '/index.php?categories');
    break;

case 'movecat':
    $cat_id = $Request->getInt('cat_id');
    if ($cat_id > 0) {
        Astore\Category::moveRow($cat_id, $actionval);
    }
    COM_refresh(ASTORE_ADMIN_URL . '/index.php?categories');
    break;

case 'delcat':
    Astore\Category::Delete($_POST['cat_id']);
    COM_refresh(ASTORE_ADMIN_URL . '/index.php?categories');
    break;

default:
    $view = isset($_REQUEST['view']) ? $_REQUEST['view'] : $action;
    break;
}

switch ($view) {
case 'edititem':
    $content .= Astore\Item::getInstance($actionval)->Edit();
    break;

case 'categories':
    $content .= Astore\Category::adminList();
    break;

case 'editcat':
    $content .= Astore\Category::getInstance($actionval)->Edit();
    break;

case 'items':
    $content .= Astore\Item::adminList($import_fld);
    break;
}

// After any action, display the item list
$msg = $Request->getInt('msg');
if ($msg > 0) {
    $content .= COM_showMessage($msg, 'astore');
}

echo COM_siteHeader('none', $LANG_ASTORE['admin_title']);
$outputHandle = outputHandler::getInstance();
$outputHandle->addScriptFile(ASTORE_PI_PATH . '/js/ajax.js');
echo Astore\Menu::Admin($view);
echo $content;
echo COM_siteFooter();
exit;
