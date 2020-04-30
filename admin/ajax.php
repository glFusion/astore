<?php
/**
 * Common admistrative AJAX functions.
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

/** Include required glFusion common functions */
require_once '../../../lib-common.php';

// This is for administrators only.  It's called by Javascript,
// so don't try to display a message
if (!SEC_hasRights('astore.admin')) {
    COM_accessLog("User {$_USER['username']} tried to illegally access the Astore admin ajax functions.");
    exit;
}

$retval = '';
switch ($_POST['action']) {
case 'toggle':
    switch ($_POST['field']) {
    case 'enabled':
        $newval = Item::toggle($_POST['oldval'], $_POST['field'], $_POST['id']);
        if ($newval != $_REQUEST['oldval']) {
            $message = sprintf($LANG_ASTORE['msg_item_updated'],
                $newval ? $LANG_ASTORE['enabled'] : $LANG_ASTORE['disabled']);
        } else {
            $message = $LANG_ASTORE['msg_item_nochange'];
        }
        break;
    }
    $retval = array(
        'id' => $_POST['id'],
        'newval' => $newval,
        'statusMessage' => $message,
    );
    break;
}

if (is_array($retval) && !empty($retval)) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    //A date in the past
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    echo json_encode($retval);
}

?>
