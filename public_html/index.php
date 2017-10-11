<?php
/**
*   Public entry page for the Amazon Astore plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

require_once '../lib-common.php';

COM_setArgNames(array('mode', 'asin'));
$mode = COM_getArgument('mode');
$asin = COM_getArgument('asin');
$content = '';

switch ($mode) {
case 'detail':
    $item = new Astore\Item($asin);
    $T = new Template(ASTORE_PI_PATH . '/templates');
    $T->set_file('detail', 'detail.thtml');
    if (!$item->isError()) {
        $listprice = $item->ListPrice('raw');
        $lowestprice = $item->LowestPrice('raw');
        if ($lowestprice && $listprice && $lowestprice->__toString() < $listprice->__toString()) {
            $T->set_var(array(
                'lowestprice'   => $item->LowestPrice(),
                'offers_url'    => $item->OffersURL(),
            ) );
        }
        $T->set_var(array(
            'item_url'  => $item->DetailURL(),
            'title'     => COM_truncate($item->Title(), 50),
            'img_url'   => $item->LargeImage()->URL,
            'img_width' => $item->LargeImage()->Width,
            'img_height' => $item->LargeImage()->Height,
            'listprice' => $item->ListPrice(),
            'iconset'   => $_CONF_ASTORE['_iconset'],
            'long_description' => $item->EditorialReview(),
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

default:
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    if (!empty($asin) && $page == 1) {
        Astore\Item::Require($asin);
    }
    $items = Astore\Item::getAll($page);
    $T = new Template(ASTORE_PI_PATH . '/templates');
    $T->set_file(array(
        'store' => 'store.thtml',
    ) );
    if (!empty($asin) && $page == 1) {
        if (!isset($items[$asin])) {
            $item = new Astore\Item($asin);
        } else {
            $item = $items[$asin];
            unset($items[$asin]);
        }
        if (!$item->isError()) {
            $T->set_block('store', 'featured', 'fb');
            $T->set_var(array(
                'item_url'  => $item->DetailURL(),
                'lowestprice' => $item->LowestPrice(),
                'listprie' => $item->ListPrice(),
                'title'     => $item->Title(),
                'img_url'   => $item->MediumImage()->URL,
                'img_width' => $item->MediumImage()->Width,
                'img_height' => $item->MediumImage()->Height,
                'formattedprice' => $item->LowestPrice(),
                'long_description' => COM_truncate($item->EditorialReview(),
                    $_CONF_ASTORE['max_feat_desc'], '...'),
                'iconset'   => $_CONF_ASTORE['_iconset'],
                'offers_url' => $item->OffersURL(),
            ) );
            $T->parse('fb', 'featured');
        }
    } else {
        $T->set_var(array(
            'store_title'   => $_CONF_ASTORE['store_title'],
        ) );
    }
    $T->set_block('store', 'productbox', 'pb');
    foreach ($items as $item) {
        if ($item->isError()) continue;
        $T->set_var(array(
            'item_url'  => $item->DetailURL(),
            'lowestprice'   => $item->LowestPrice(),
            'listprice' => $item->ListPrice(),
            //'title'     => $item->Title(),
            'title'     => COM_truncate($item->Title(),
                    $_CONF_ASTORE['max_blk_desc'], '...'),
            'img_url'   => $item->MediumImage()->URL,
            'img_width' => $item->MediumImage()->Width,
            'img_height' => $item->MediumImage()->Height,
            'formattedprice' => $item->LowestPrice(),
            'iconset'   => $_CONF_ASTORE['_iconset'],
            'long_description' => '',
            'offers_url' => $item->OffersURL(),
        ) );
        $T->parse('pb', 'productbox', true);
    }

    // Display pagination
    $count = Astore\Item::Count();
    $pagenav_args = '';
    if (isset($_CONF_ASTORE['perpage']) &&
            $_CONF_ASTORE['perpage'] > 0 &&
            $count > $$_CONF_ASTORE['perpage'] ) {
        $T->set_var('pagination',
            COM_printPageNavigation(ASTORE_URL . '/index.php' . $pagenav_args,
                        $page,
                        ceil($count / $_CONF_ASTORE['perpage'])));
    } else {
        $T->set_var('pagination', '');
    }

    $T->parse('output', 'store');
    $content .= $T->finish($T->get_var('output'));
    break;
}

echo COM_siteHeader();
echo $content;
echo COM_siteFooter();

?>
