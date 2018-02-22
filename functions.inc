<?php
/**
*   glFusion API functions for the Astore plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017-2018 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.2.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Global configuration array, in case this is included within a function
*   @global array
*/
global $_CONF_ASTORE;

define ('ASTORE_PI_PATH', __DIR__);
define ('ASTORE_MAX_QUERY', 10);    // Amazon max items in a query

$langfile = $_CONF['language'] . '.php';
if (!file_exists(ASTORE_PI_PATH . '/language/' . $langfile)) {
    $langfile = 'english.php';
}
/** Import the plugin's language file */
require_once ASTORE_PI_PATH . '/language/' . $langfile;

$ast_config = config::get_instance();
$_CONF_ASTORE = $ast_config->get_config('astore');

/** Import static config values */
require_once ASTORE_PI_PATH . '/astore.php';
if (file_exists(ASTORE_PI_PATH . '/config.php')) {
    /** Import local configuration overrides, if any */
    include_once ASTORE_PI_PATH . '/config.php';
}

// Mark whether this is a uikit theme. Save to global conf var
// so it's available where needed.
$_CONF_ASTORE['_is_uikit'] = $_SYSTEM['framework'] == 'uikit' ? true : false;
$_CONF_ASTORE['_iconset'] = $_CONF_ASTORE['_is_uikit'] ? 'uk-icon' : 'fa fa';

define ('ASTORE_ADMIN_URL',
        $_CONF['site_admin_url'] . '/plugins/'.$_CONF_ASTORE['pi_name']);
define ('ASTORE_URL',
        $_CONF['site_url'] . '/'.$_CONF_ASTORE['pi_name']);
define ('ASTORE_CLASSPATH', ASTORE_PI_PATH . '/classes');

spl_autoload_register(function ($class)
{
    if (strpos($class, 'Astore\\') === 0) {
        $class = str_replace('Astore\\', '', $class);
        $path = __DIR__ . '/classes/' . $class . '.class.php';
        if (file_exists($path)) {
            include $path;
        }
    }
});


/**
*   Create the centerblock, if needed
*   @param  integer $where  Which area is being displayed now
*   @param  integer $page   Page number
*   @param  string  $topic  Topic ID, or empty string
*   @return string          HTML for centerblock
*/
function plugin_centerblock_astore($where=1,$page=1,$topic ='')
{
    global $_CONF_ASTORE;

    // Flag whether we're on the homepage or not
    $on_homepage = ($page == 1 && $topic == '') ? true : false;
    $display = '';

    // Centerblock must be enabled, and only works on the homepage
    if (isset($_CONF_ASTORE['cb_enable']) && $_CONF_ASTORE['cb_enable'] &&
            $on_homepage) {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $display .= COM_siteHeader();
        $display .= ASTORE_show($page);
        $display .= COM_siteFooter();
    }
    return $display;
}


/**
*   Called by the plugin Editor to display the current plugin code version
*   This may be different than the version installed and registered currently.
*   If newer then you may want to run the update
*
*   @return string  version number
*/
function plugin_chkVersion_astore()
{
    global $_CONF_ASTORE;
    return $_CONF_ASTORE['pi_version'];
}


/**
*   This will put an option for astore in the command and control block on
*   moderation.php
*
*   @return mixed   array(title, url, icon), or boolean false when not allowed
*/
function plugin_cclabel_astore()
{
    global $_CONF_ASTORE;

    if (SEC_hasRights ('astore.admin')) {
        return array($_CONF_ASTORE['pi_display_name'],
                      ASTORE_ADMIN_URL . '/index.php',
                      plugin_geticon_astore());
    }
    return false;
}


/**
*   Returns the main menu items for the plugin.
*
*   @return array   Associative array of menu items to be added to menu
*/
function plugin_getmenuitems_astore()
{
    global $_CONF, $_CONF_ASTORE;

    $menuitems = array();
    $a = $_CONF_ASTORE['pi_display_name'] .
        '&nbsp;<i class="' . ASTORE_getIcon('amazon') . '"></i>';
    $menuitems[$a] = ASTORE_URL . '/index.php';
    return $menuitems;
}


/**
*   Returns the administrative option for this plugin
*
*   @return mixed   array(title, url, num. astore), or false when not allowed
*/
function plugin_getadminoption_astore()
{
    global $_TABLES, $_CONF_ASTORE;

    if (SEC_hasRights('astore.admin')) {
        $total_astore = DB_count($_TABLES['astore_catalog']);
        return array ($_CONF_ASTORE['pi_display_name'],
                      ASTORE_ADMIN_URL . '/index.php',
                      $total_astore);
    }
    return false;
}


/**
*   Update the Astore plugin
*
*   @return integer Message number to display (true = generic success msg)
*/
function plugin_upgrade_astore()
{
    require_once __DIR__ . '/upgrade.inc.php';
    return astore_do_upgrade();
}


/**
*   Make changes needed when the plugin is enabled or disabled.
*
*   Uses the PHP block function name instead of the block name to
*   catch user-created blocks that rely on this plugin.
*
*   @param  boolean $enable     True if we're being enabled, False otherwise
*/
function plugin_enablestatechange_astore($enabled)
{
    global $_TABLES, $_PLUGINS;

    $enabled = $enabled == 1 ? 1 : 0;
    $blockfns = array('phpblock_astore_');

    // If trying to enable the plugin but lgLib is not available,
    // take no action and return
    if ($enabled && !in_array('lglib', $_PLUGINS)) {
        // Re-disable the plugin
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', 'astore');
        $enable = false;
        $enabled = 0;
    }

    if ($enabled == 0) {
        foreach ($blockfns as $blockfn) {
        $sql = "UPDATE {$_TABLES['blocks']}
                SET is_enabled=$enabled
                WHERE phpblockfn like '{$blockfn}%'";
        DB_query($sql);
        }
    }
}


/**
*   Receives notification of anothe plugin's deletion
*
*   @param  mixed   $id     Item ID
*   @param  string  $type   Plugin Name
*/
function plugin_itemdeleted_astore($id, $type)
{
    global $_TABLES;

    // If the lgLib plugin is disabled or removed, disable thie plugin
    if ($type == 'lglib' && $id == '*') {
        plugin_enablestatechange_astore(false);
        DB_change($_TABLES['plugins'], 'pi_enabled', 0, 'pi_name', 'astore');
    }
}


/**
*   Checks that the current user has plugin moderator rights.
*
*   @return boolean     Returns true if moderator
*/
function plugin_ismoderator_astore()
{
    static $isMod = NULL;

    if ($isMod === NULL) {
        $isMod = plugin_isadmin_astore();
    }
    return $isMod;
}


/**
*   Checks that the current user has plugin admin rights.
*   Not a glFusion core function, but similar
*
*   @return boolean     Returns true if administrator
*/
function plugin_isadmin_astore()
{
    static $isAdmin = NULL;

    if ($isAdmin === NULL) {
        $isAdmin = SEC_hasRights('astore.admin');
    }
    return $isAdmin;
}


/**
* Returns the URL of the plugin's icon
*
* @return   string      URL of the icon
*/
function plugin_geticon_astore()
{
    return ASTORE_URL . '/images/astore.png';
}


/**
*   Automatic uninstall function for plugins
*
*   This code is automatically uninstalling the plugin.
*   It passes an array to the core code function that removes
*   tables, groups, features and php blocks from the tables.
*   Additionally, this code can perform special actions that cannot be
*   foreseen by the core code (interactions with other plugins for example)
*
*   @return   array     Elements to be removed
*/
function plugin_autouninstall_astore ()
{
    global $_CONF_ASTORE;

    $out = array (
        // give the name of the tables, without $_TABLES
        'tables' => array(
                'astore_catalog',
                'astore_cache',
        ),

        // give the full name of the group, as in the db
        'groups' => array('astore Admin'),

        // give the full name of the feature, as in the db
        'features' => array(
                'astore.admin',
        ),

        // give the full name of the block, including 'phpblock_', etc
        'php_blocks' => array(
                'phpblock_astore_random',
        ),

        // give all vars with their name
        'vars'=> array('astore_ts')
    );
    Astore\Cache::clearCache();
    return $out;
}


/**
 *  Returns a random astore that belongs to categories associated with
 *  the current topic
 *
 *  @see    BANNER_block_astore()
 *  @return string      HTML for astore display.
 */
/*function phpblock_astore_topic_random($A= array(), $param='')
{
    $extra = array('limit' => '1');

    if (!empty($param)) {
        $extra['type'] = $param;
    } else {
        $extra['type'] = 'block';
    }
    return BANNER_block_astore($extra);
}*/


/**
 *  Get additional CSS into the site header.
 *
 *  @return array List of paths to Javascript files
 */
function plugin_getheadercss_astore()
{
    global $_CONF, $_CONF_ASTORE;

    $styles = array();
    $files = array(
        'style.css',
    );

    $path = __DIR__ . '/css';
    foreach ($files as $file) {
        $styles[] = $path . '/' . $file;
    }

    $path .= '/custom';
    foreach ($files as $file) {
        if (file_exists($path . '/' . $file)) {
            $styles[] = $path . '/' . $file;
        }
    }
    return $styles;
}


/**
*   Get the URL to the configuration help file
*
*   @param  string  $doclang    Document language
*   @return string              URL to document
*/
function plugin_getconfigelementhelp_astore($option, $doclang='english')
{
    global $_CONF_ASTORE;

    $url = LGLIB_getDocUrl('config', $_CONF_ASTORE['pi_name'], $doclang);

    if (!empty($option)) {
        $url .= '#desc_' . $option;
    }

    return array($url, 0);
}


/**
*   Encrypt the AWS secret key config item
*
*   @param  string  $value  Secret Key
*   @return string          Encrypted value
*/
function plugin_configmanager_aws_secret_key_astore_validate($value)
{
    global $_VARS;
    if (isset($_VARS['guid'])) {
        $value = COM_encrypt($value,$_VARS['guid']);
    }
    return $value;
}


/**
*   Perform scheduled functions, such as removing old cache entries
*/
function plugin_runScheduledTask_astore()
{
    global $_TABLES;

    DB_query("DELETE FROM {$_TABLES['astore_cache']}
            WHERE exp < UNIX_TIMESTAMP()");
}


/**
*   Implements the autotags
*   - astore:       Display a product box
*   - astore_link:  Display a text link
*
*   @param  string  $op         operation to perform
*   @param  string  $content    item (e.g. story text), including the autotag
*   @param  array   $autotag    parameters used in the autotag
*   @param  mixed               tag names (for $op='tagname') or formatted content
*/
function plugin_autotags_astore($op, $content = '', $autotag = '')
{
    switch ($op) {
    case 'tagname':
        return array('astore', 'astore_link');
        break;
    case 'parse':
        $HTML = '';
        switch ($autotag['tag']) {
        case 'astore':
            // Get a specific product
            $Item = new Astore\Item($autotag['parm1']);
            $HTML = Astore\Item::showProducts($Item);
            break;
        case 'astore_link':
            $Item = new Astore\Item($autotag['parm1']);
            if ($Item->Data()) {        // Make sure some valid data was returned
                $desc = empty($autotag['parm2']) ? $Item->Title() : $autotag['parm2'];
                $HTML = COM_createLink($desc, $Item->DetailPageURL(),
                    array('target' => '_blank'));
            }
            break;
        }
        $content = str_replace($autotag['tagstr'], $HTML, $content);
        return $content;
    }
}


////////////////////////////////////////////
//
//  Public Plugin-Specific Functions Follow
//
////////////////////////////////////////////

/**
*   Get an icon from the CSS icon set, either UIkit or FontAwesome
*
*   @param  string  $name   Base name of icon, e.g. "edit" for "uk-icon-edit"
*   @param  string  $style  Style name from local CSS
*   @return string      Class names for icon
*/
function ASTORE_getIcon($name, $style='info')
{
    global $_CONF_ASTORE;

    if ($style != '')
        $style = ' ast-icon-' . $style;

    if ($_CONF_ASTORE['_is_uikit']) {
        return 'uk-icon uk-icon-' . $name . $style;
    } else {
        return 'fa fa-' . $name . $style;
    }
}

function ASTORE_show($page = 1, $asin='')
{
    global $_CONF_ASTORE;

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
    return $T->finish($T->get_var('output'));
}


/**
*   Get item information.
*
*   @param  array   $args       Arguments
*   @param  array   &$output    Pointer to output value
*   @param  array   &$svc_msg   Unused
*   @return integer             Status code
*/
function service_getiteminfo_astore($args, &$output, &$svc_msg)
{
    $output = NULL;
    if (!isset($args['keytype']) || !isset($args['keyval'])) {
        return PLG_RET_ERROR;
    }

    switch(strtolower($args['keytype'])) {
    case 'isbn':
        $output = Astore\Item::getByISBN($args['keyval']);
        if (empty($output)) {
            return PLG_RET_PRECONDITION_FAILED;
        }
        break;
    default:
        return PLG_RET_ERROR;
    }
    return PLG_RET_OK;
}

?>
