<?php
/**
 * Display the detail page for a single item.
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

require_once '../lib-common.php';

if (!$_CONF_ASTORE['is_open']) {
    COM_404();
}

// Tell search engines not to index, may use excessive requests
$outputHandle = outputHandler::getInstance();
$outputHandle->addMeta('name', 'robots', 'noindex,nofollow');

COM_setArgNames(array('asin'));
$asin = COM_getArgument('asin');

if (empty($asin)) {
    COM_404();
}

$Item = Astore\Item::getInstance($asin);
if (!$Item->isValid()) {
    COM_404();
}
$content = $Item->detailPage();
echo COM_siteHeader();
echo $content;
echo COM_siteFooter();
