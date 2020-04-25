<?php
/**
 * Class to provide admin and user-facing menus.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2020 Lee Garner <lee@leegarner.com>
 * @package     shop
 * @version     v0.2.0
 * @since       v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore;


/**
 * Class to provide admin and user-facing menus.
 * @package shop
 */
class Menu
{
    /**
     * Create the user menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function User($view='')
    {
        global $_CONF, $LANG_SHOP, $_SHOP_CONF;

        USES_lib_admin();

        $hdr_txt = SHOP_getVar($LANG_SHOP, 'user_hdr_' . $view);
        $menu_arr = array(
            array(
                'url'  => SHOP_URL . '/index.php',
                'text' => $LANG_SHOP['back_to_catalog'],
            ),
        );

        $active = $view == 'orderhist' ? true : false;
        $menu_arr[] = array(
            'url'  => COM_buildUrl(SHOP_URL . '/account.php'),
            'text' => $LANG_SHOP['purchase_history'],
            'active' => $active,
        );

        // Show the Gift Cards menu item only if enabled.
        if ($_SHOP_CONF['gc_enabled']) {
            $active = $view == 'couponlog' ? true : false;
            $menu_arr[] = array(
                'url'  => COM_buildUrl(SHOP_URL . '/account.php?mode=couponlog'),
                'text' => $LANG_SHOP['gc_activity'],
                'active' => $active,
                'link_admin' => plugin_ismoderator_shop(),
            );
        }
        return \ADMIN_createMenu($menu_arr, $hdr_txt);
    }


    /**
     * Create the administrator menu.
     *
     * @param   string  $view   View being shown, so set the help text
     * @return  string      Administrator menu
     */
    public static function Admin($view='')
    {
        global $_CONF, $LANG_ADMIN, $LANG_ASTORE, $_CONF_ASTORE;

        $menu_arr = array(
            array(
                'url'  => ASTORE_ADMIN_URL . '/index.php',
                'text' => $LANG_ASTORE['items'],
                'active' => $view == 'items' ? true : false,
            ),
            array(
                'url'  => ASTORE_ADMIN_URL . '/index.php?categories=x',
                'text' => $LANG_ASTORE['categories'],
                'active' => $view == 'categories' ? true : false,
            ),
            array(
                'url'   => ASTORE_ADMIN_URL . '/index.php?exportcsv=x',
                'text'  => $LANG_ASTORE['export'],
            ),
            array(
                'url'   => ASTORE_ADMIN_URL . '/index.php?clearcache=x',
                'text'  => $LANG_ASTORE['clearcache'],
            ),
            array(
                'url'  => $_CONF['site_admin_url'],
                'text' => $LANG_ADMIN['admin_home'],
            ),
        );

        $T = new \Template(ASTORE_PI_PATH . '/templates');
        $T->set_file('title', 'admin.thtml');
        $T->set_var(array(
            'version'   => $_CONF_ASTORE['pi_version'],
            'icon' => plugin_geticon_astore(),
        ) );
        $retval = $T->parse('', 'title');
        $retval .= ADMIN_createMenu(
            $menu_arr, '', plugin_geticon_astore()
        );
        return $retval;
    }

}

?>


