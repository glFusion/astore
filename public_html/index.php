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
    if (!$item->isError()) {
        $T = new Template(ASTORE_PI_PATH . '/templates');
        $T->set_file('detail', 'detail.thtml');
        $T->set_var(array(
            'item_url'  => $item->DetailURL(),
            'price'     => $item->FormattedPrice(),
            'title'     => COM_truncate($item->Title(), 50),
            'img_url'   => $item->MediumImage()->URL,
            'img_width' => $item->MediumImage()->Width,
            'img_height' => $item->MediumImage()->Height,
            'formattedprice' => $item->FormattedPrice(),
            'iconset'   => $_CONF_ASTORE['_iconset'],
            'long_description' => $item->EditorialReview(),
        ) );
        $T->parse('output', 'detail');
        $content .= $T->finish($T->get_var('output'));
    } else {
        $content .= "not found";
    }
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
        $T->set_block('store', 'featured', 'fb');
        $T->set_var(array(
            'item_url'  => $item->DetailURL(),
            'price'     => $item->FormattedPrice(),
            'title'     => $item->Title(),
            'img_url'   => $item->MediumImage()->URL,
            'img_width' => $item->MediumImage()->Width,
            'img_height' => $item->MediumImage()->Height,
            'formattedprice' => $item->FormattedPrice(),
            'long_description' => COM_truncate($item->EditorialReview(),
                    $_CONF_ASTORE['max_feat_desc'], '...'),
            'iconset'   => $_CONF_ASTORE['_iconset'],
            'offers_url' => $item->OffersURL(),
        ) );
        $T->parse('fb', 'featured');
    }

    $T->set_block('store', 'productbox', 'pb');
    foreach ($items as $item) {
        //$item = new Astore\Item($asin);
        $T->set_var(array(
            'item_url'  => $item->DetailURL(),
            'price'     => $item->FormattedPrice(),
            //'title'     => $item->Title(),
            'title'     => COM_truncate($item->Title(),
                    $_CONF_ASTORE['max_blk_desc'], '...'),
            'img_url'   => $item->MediumImage()->URL,
            'img_width' => $item->MediumImage()->Width,
            'img_height' => $item->MediumImage()->Height,
            'formattedprice' => $item->FormattedPrice(),
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


    $T->set_var(array(
        'store_title'   => $_CONF_ASTORE['store_title'],
    ) );
    $T->parse('output', 'store');
    $content .= $T->finish($T->get_var('output'));
    break;
}

echo COM_siteHeader();
echo $content;
echo COM_siteFooter();

?>
