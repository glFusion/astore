<?php
/**
 * Public entry page for the Amazon Astore plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2023 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     0.3.0
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

COM_setArgNames(array('mode', 'asin'));
$mode = COM_getArgument('mode');
$asin = COM_getArgument('asin');
$content = '';
$Request = new Astore\Models\Request;
$query = $Request->getString('query');

switch ($mode) {
case 'detail':
    $item = new Astore\Item($asin);
    $T = new Template(ASTORE_PI_PATH . '/templates');
    $T->set_file('detail', 'detail.thtml');
    if (!$item->isError()) {
        $listprice = $item->ListPrice('raw');
        $lowestprice = $item->LowestPrice('raw');
        if (
            ($lowestprice && $listprice && ($lowestprice < $listprice)) ||
            ($lowestprice && !$listprice) ) {
            $T->set_var(array(
                'show_lowest' => true,
            ) );
        }
        $T->set_var(array(
            'item_url'  => $item->DetailPageURL(),
            'title'     => $item->Title(),
            'img_url'   => $item->LargeImage()->URL,
            'img_width' => $item->LargeImage()->Width,
            'img_height' => $item->LargeImage()->Height,
            'listprice' => $item->ListPrice(),
            'lowestprice' => $item->LowestPrice(),
            'long_description' => $item->EditorialReview(),
            'available' => $item->isAvailable(),
            'offers_url'    => $item->OffersURL(),
            'is_prime'  => $item->isPrime(),
        ) );
        $features = $item->Features();
        if (!empty($features)) {
            $T->set_var('has_features', true);
            $T->set_block('detail', 'Features', 'fb');
            foreach ($features as $feature) {
                $T->set_var('feature', $feature);
                $T->parse('fb', 'Features', true);
            }
        }
    } else {
        $T->set_var(array(
            'message'   => $LANG_ASTORE['item_not_found'],
            'msg_class' => 'danger',
        ) );
    }
    $T->parse('output', 'detail');
    $content .= $T->finish($T->get_var('output'));
    break;

case 'search':
    $page = $Request->getInt('page', 1);
    if ($page < 1) $page = 1;
    $query = trim($query);
    if (!empty($query)) {
        $S = new Astore\Catalog();
        $content .= $S->renderSearch($query, $page);
        break;
    }
default:
    if (!empty($asin) && $page == 1) {
        Astore\Item::RequireASIN($asin);
    }
    $page = $Request->getInt('page', 1);
    if ($page < 1) $page = 1;
    $Catalog = new Astore\Catalog;
    $Catalog->addCats($Request->getArray('cats');
    $content .= $Catalog->setASIN($asin)->renderItems($page);
    break;
}

echo COM_siteHeader();
echo $content;
echo COM_siteFooter();
