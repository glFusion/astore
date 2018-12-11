<?php
/**
 * Public entry page for the Amazon Astore plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     0.1.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
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
        if (
            ($lowestprice && $listprice && $lowestprice < $listprice) ||
            ($lowestprice && !$listprice) ) {
            $T->set_var(array(
                'lowestprice'   => $item->LowestPrice(),
            ) );
        }
        $T->set_var(array(
            'item_url'  => $item->DetailPageURL(),
            'title'     => $item->Title(),
            'img_url'   => $item->LargeImage()->URL,
            'img_width' => $item->LargeImage()->Width,
            'img_height' => $item->LargeImage()->Height,
            'listprice' => $item->ListPrice(),
            'iconset'   => $_CONF_ASTORE['_iconset'],
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
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $query = isset($_POST['query']) ? $_POST['query'] : $asin;
    $query = trim($query);
    $S = new Astore\Search();
    $items = $S->doSearch($query, $page);
    $T = new Template(ASTORE_PI_PATH . '/templates');
    $T->set_file(array(
        'search' => 'search.thtml',
        'moreresults' => 'moreresults.thtml',
    ) );
    $T->set_var('productboxes', Astore\Item::showProducts($items));
    $T->parse('output', 'search');
    $content .= $T->finish($T->get_var('output'));
    if ($S->Pages() > 1) {
        $T->set_var('moreresults_url', $S->MoreResultsURL());
        $T->parse('moreresults', 'moreresults');
        $content .= $T->finish($T->get_var('moreresults'));
    }
    break;

default:
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    if (!empty($asin) && $page == 1) {
        Astore\Item::RequireASIN($asin);
    }
    $items = Astore\Item::getAll($page);
/*    foreach ($items as $x) {
        echo $x->asin . "<br />\n";
    }
    exit;*/
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
                'item_url'  => $item->DetailPageURL(),
                'lowestprice' => $item->LowestPrice(),
                'listprice' => $item->ListPrice(),
                'title'     => $item->Title(),
                'img_url'   => $item->MediumImage()->URL,
                'img_width' => $item->MediumImage()->Width,
                'img_height' => $item->MediumImage()->Height,
                'formattedprice' => $item->LowestPrice(),
                'long_description' => COM_truncate($item->EditorialReview(),
                    $_CONF_ASTORE['max_feat_desc'], '...'),
                'iconset'   => $_CONF_ASTORE['_iconset'],
                'offers_url' => $item->OffersURL(),
                'available' => $item->isAvailable(),
            ) );
            $T->parse('fb', 'featured');
        }
    } else {
        $T->set_var(array(
            'store_title'   => $_CONF_ASTORE['store_title'],
        ) );
    }

    $T->set_var('productboxes', Astore\Item::showProducts($items));

    // Display pagination
    $count = Astore\Item::Count();
    $pagenav_args = '';
    if (isset($_CONF_ASTORE['perpage']) &&
            $_CONF_ASTORE['perpage'] > 0 &&
            $count > $_CONF_ASTORE['perpage'] ) {
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


/**
 * Display products in a grid.
 *
 * @deprecated
 * @param   array   $items  Array of items to show
 * @return  string      HTML for product list
 */
function ASTORE_showProducts($items)
{
    global $_CONF_ASTORE;

    $T = new Template(ASTORE_PI_PATH . '/templates');
    $T->set_file(array(
        'products' => 'productbox.thtml',
    ) );
    $T->set_block('products', 'productbox', 'pb');
    foreach ($items as $item) {
        if ($item->isError()) continue;
        if (!$item->isAvailable()) {
            continue;
        }
        $T->set_var(array(
            'item_url'  => $item->DetailPageURL(),
            'lowestprice'   => $item->LowestPrice(),
            'listprice' => $item->ListPrice(),
            'title'     => COM_truncate($item->Title(),
                    $_CONF_ASTORE['max_blk_desc'], '...'),
            'img_url'   => $item->MediumImage()->URL,
            'img_width' => $item->MediumImage()->Width,
            'img_height' => $item->MediumImage()->Height,
            'formattedprice' => $item->LowestPrice(),
            'displayprice' => $item->DisplayPrice(),
            'iconset'   => $_CONF_ASTORE['_iconset'],
            'long_description' => '',
            'offers_url' => $item->OffersURL(),
            'available' => $item->isAvailable(),
        ) );
        $T->parse('pb', 'productbox', true);
    }
    $T->parse('output', 'products');
    return $T->finish($T->get_var('output'));
}

echo COM_siteHeader();
echo $content;
echo COM_siteFooter();

?>
