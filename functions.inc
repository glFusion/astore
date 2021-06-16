<?php
/**
 * glFusion API functions for the Astore plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2020 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/**
 * Global configuration array, in case this is included within a function.
 * @global array
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

define ('ASTORE_ADMIN_URL',
        $_CONF['site_admin_url'] . '/plugins/'.$_CONF_ASTORE['pi_name']);
define ('ASTORE_URL',
    $_CONF['site_url'] . '/'.$_CONF_ASTORE['pi_name']);
define ('ASTORE_CLASSPATH', ASTORE_PI_PATH . '/classes');

spl_autoload_register(function ($class)
{
    // project-specific namespace prefix
    $prefix = 'Astore\\';

    // does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }

    // base directory for the namespace prefix
    $base_dir = __DIR__ . '/classes/';

    // get the relative class name
    $relative_class = substr($class, $len);

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.class.php';

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});
use Astore\MO;


/**
 * Create the centerblock, if needed.
 *
 * @param   integer $where  Which area is being displayed now
 * @param   integer $page   Page number
 * @param   string  $topic  Topic ID, or empty string
 * @return  string          HTML for centerblock
 */
function plugin_centerblock_astore($where=1,$page=1,$topic ='')
{
    global $_CONF_ASTORE;

    // Flag whether we're on the homepage or not
    $on_homepage = ($page == 1 && $topic == '') ? true : false;
    $display = '';

    // Centerblock must be enabled, and only works on the homepage
    if (
        isset($_CONF_ASTORE['cb_enable']) &&
        $_CONF_ASTORE['cb_enable'] &&
        $on_homepage
    ) {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $Catalog = new Astore\Catalog;
        $display .= COM_siteHeader();
        $display .= $Catalog->Render($page);
        $display .= COM_siteFooter();
    }
    return $display;
}


/**
 * Called by the plugin Editor to display the current plugin code version.
 * This may be different than the version installed and registered currently.
 * If newer then you may want to run the update.
 *
 * @return  string  version number
 */
function plugin_chkVersion_astore()
{
    global $_CONF_ASTORE;
    return $_CONF_ASTORE['pi_version'];
}


/**
 * This will put an option for astore in the command and control block on moderation.php.
 *
 * @return  mixed   array(title, url, icon), or boolean false when not allowed
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
 * Returns the main menu items for the plugin.
 *
 * @return  array   Associative array of menu items to be added to menu
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
 * Returns the administrative option for this plugin.
 *
 * @return  mixed   array(title, url, num. astore), or false when not allowed
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
 * Update the Astore plugin.
 *
 * @return  integer Message number to display (true = generic success msg)
 */
function plugin_upgrade_astore()
{
    require_once __DIR__ . '/upgrade.inc.php';
    return astore_do_upgrade(false);
}


/**
 * Make changes needed when the plugin is enabled or disabled.
 *
 * Uses the PHP block function name instead of the block name to
 * catch user-created blocks that rely on this plugin.
 *
 * @param   boolean $enabled    True if we're being enabled, False otherwise
 */
function plugin_enablestatechange_astore($enabled)
{
    global $_TABLES, $_PLUGINS;

    $enabled = $enabled == 1 ? 1 : 0;
    $blockfns = array('phpblock_astore_');

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
 * Receives notification of anothe plugin's deletion.
 *
 * @param  mixed   $id     Item ID
 * @param  string  $type   Plugin Name
 */
function plugin_itemdeleted_astore($id, $type)
{
}


/**
 * Checks that the current user has plugin moderator rights.
 *
 * @return  boolean     Returns true if moderator
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
 * Checks that the current user has plugin admin rights.
 * Not a glFusion core function, but similar
 *
 * @return  boolean     Returns true if administrator
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
 * Returns the URL of the plugin's icon.
 *
 * @return  string      URL of the icon
 */
function plugin_geticon_astore()
{
    return ASTORE_URL . '/images/astore.png';
}


/**
 * Automatic uninstall function for plugins.
 *
 * This code is automatically uninstalling the plugin.
 * It passes an array to the core code function that removes
 * tables, groups, features and php blocks from the tables.
 * Additionally, this code can perform special actions that cannot be
 * foreseen by the core code (interactions with other plugins for example)
 *
 * @return  array     Elements to be removed
 */
function plugin_autouninstall_astore ()
{
    global $_CONF_ASTORE;

    $out = array (
        // give the name of the tables, without $_TABLES
        'tables' => array(
            'astore_catalog',
            'astore_cache',
            'astore_categories',
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
    Astore\Cache::clear();
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
 * Get additional CSS into the site header.
 *
 * @return  array List of paths to Javascript files
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
 * Get additional Javascript into the site header.
 *
 * @return  array List of paths to Javascript files
 */
function plugin_getheaderjs_astore()
{
    return array(
        __DIR__ . '/js/ajax.js',
    );
}


/**
 * Get the URL to the configuration help file.
 *
 * @param   string  $option     Help option to display
 * @param   string  $doclang    Document language
 * @return  string              URL to document
 */
function plugin_getconfigelementhelp_astore($option, $doclang='english')
{
    $url = ASTORE_getDocUrl('config', $doclang);
    if (!empty($option)) {
        $url .= '#desc_' . $option;
    }
    return array($url, 0);
}


/**
 * Encrypt the AWS secret key config item.
 *
 * @param   string  $value  Secret Key
 * @return  string          Encrypted value
 */
function plugin_configmanager_aws_secret_key_astore_validate($value)
{
    global $_VARS;
    if (version_compare('GVERSION', '2.0.0', '<') && isset($_VARS['guid'])) {
        $value = COM_encrypt($value,$_VARS['guid']);
    }
    return $value;
}


/**
 * Get a selection of groups for current and arrears members.
 *
 * @see     plugin_configmanager_select_member_all_group_membership()
 * @see     plugin_configmanager_select_member_group_membership()
 * @return  array   Array of groups
 */
function plugin_configmanager_select_grp_search_astore()
{
    global $_TABLES;

    $A = array();
    $query = "SELECT grp_id, grp_name FROM {$_TABLES['groups']}";
    $result = DB_query($query);
    while ($row = DB_fetchArray($result)) {
        $row['grp_name'] = htmlspecialchars($row['grp_name']);
        $A[$row['grp_name']] = $row['grp_id'];
    }
    return $A;
}


/**
 * Create the config selection for the default category ID
 *
 * @return  array   Array of cat_name=>cat_id
 */
function plugin_configmanager_select_def_catid_astore()
{
    global $_TABLES;

    $A = array();
    $sql = "SELECT cat_id, cat_name FROM {$_TABLES['astore_categories']}";
    $result = DB_query($sql);
    while ($row = DB_fetchArray($result)) {
        $row['cat_name'] = htmlspecialchars($row['cat_name']);
        $A[$row['cat_name']] = $row['cat_id'];
    }
    return $A;
}


/**
 * Validate that the cache time setting is between 10 minutes and 4 hours.
 *
 * @param   string  $val    Admin-entered value
 * @return  integer     Validated cache time
 */
function plugin_configmanager_aws_cache_min_astore_validate($val)
{
    $val = (int)$val;
    if ($val == 0) {
        // zero is ok, indicates caching is disabled
    } elseif ($val < 10) {
        $val = 60;
    } elseif ($val > 239) {
        $val = 120;
    }
    return $val;
}


/**
 * Perform scheduled functions, such as removing old cache entries.
 */
function plugin_runScheduledTask_astore()
{
    global $_TABLES;

    DB_query("DELETE FROM {$_TABLES['astore_cache']}
            WHERE exp < UNIX_TIMESTAMP()");
}


/**
 * Implements the autotags.
 * - astore:       Display a product box
 * - astore_link:  Display a text link
 *
 * @param   string  $op         operation to perform
 * @param   string  $content    item (e.g. story text), including the autotag
 * @param   array   $autotag    parameters used in the autotag
 * @param   mixed               tag names (for $op='tagname') or formatted content
 */
function plugin_autotags_astore($op, $content = '', $autotag = '')
{
    global $LANG_ASTORE_AUTOTAG;

    switch ($op) {
    case 'tagname':
        return array('astore', 'astore_link');
        break;
    case 'desc':
        switch ($content) {
        case 'astore':
            return MO::_('Include an item link from the store or amazon.');
            break;
        case 'astore_link':
            return MO::_('Create a link to an item in the store.');
            break;
        default :
            return '';
            break;
        }
        break;
    case 'parse':
        $HTML = '';
        $skip = 0;
        switch ($autotag['tag']) {
        case 'astore':
            $cls = '\\Astore\\Autotags\\' . $autotag['parm1'];
            if (!class_exists($cls)) {
                return '';
            }
            $AT = new $cls;
            $HTML = $AT->withAutotag($autotag)->parse();
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


/**
 * Return true to enable service functions.
 *
 * @return  boolean     True to enable web services
 */
function plugin_wsEnabled_astore()
{
    return true;
}


////////////////////////////////////////////
//
//  Public Plugin-Specific Functions Follow
//
////////////////////////////////////////////

/**
 * Get an icon from the CSS icon set, either UIkit or FontAwesome.
 *
 * @param   string  $name   Base name of icon, e.g. "edit" for "uk-icon-edit"
 * @param   string  $style  Style name from local CSS
 * @return  string      Class names for icon
 */
function ASTORE_getIcon($name, $style='info')
{
    global $_CONF_ASTORE;

    if ($style != '')
        $style = ' ast-icon-' . $style;

    return 'uk-icon uk-icon-' . $name . $style;
}


/**
 * Get item information.
 *
 * @param   array   $args       Arguments
 * @param   array   $output     Pointer to output value
 * @param   array   $svc_msg    Unused
 * @return  integer             Status code
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


/**
 * Get the link to a documentation file.
 * Returns the URL to the file, or to the directory if no file requested.
 *
 * @param   string  $file       HTML file
 * @param   string  $doclang    Documentation language, default English
 * @return  s tring              URL to document directory or file
 */
function ASTORE_getDocUrl($file='', $doclang='english')
{
    global $_CONF;

    $doc_path = "{$_CONF['path_html']}astore/docs/$doclang/";
    $doc_url = '';

    if ($file != '') {
        $file .= '.html';
        if (!is_file($doc_path . $file)) {
            $doclang = 'english';
        }
        $doc_url = "{$_CONF['site_url']}/astore/docs/$doclang/$file";
    } else {
        if (!is_dir($doc_path)) {
            $doclang = 'english';
        }
        $doc_url = "{$_CONF['site_url']}/astore/docs/$doclang/";
    }
    return $doc_url;
}


/**
 * Get a form or URL parameter.
 * Checks $_POST, then $_GET, and returns the raw value if found.
 * Returns NULL if the parameter is not set.
 * If `array` is specified and the parameter is a string, then it is
 * assumed to be a comma-separated set of values.
 *
 * @param   string  $name   Name of parameter
 * @param   string  $type   Type of parameter, default "string"
 * @return  mixed       Value of parameter, or NULL if not found
 */
function ASTORE_getParam($name, $type = 'string')
{
    if (isset($_POST[$name])) {
        $value = $_POST[$name];
    } elseif (isset($_GET[$name])) {
        $value = $_GET[$name];
    } else {
        return NULL;
    }

    switch($type) {
    case 'int':
        return (int)$value;
        break;
    case 'array':
        if (is_string($value)) {
            $value = explode(',', $value);
        } elseif (is_array($value)) {
            $vals = array();
            foreach($value as $val) {
                if (strpos($val, ',') > 0) {
                    $vals = array_merge($vals, explode(',', $val));
                } else {
                    $vals[] = $val;
                }
            }
            $value = array_unique($vals);
        }
    default:
        return $value;
        break;
    }
}



?>
