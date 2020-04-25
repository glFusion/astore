<?php
/**
*   Table definitions and other static config variables.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017-2018 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.2.0
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   Global array of table names from glFusion
*   @global array $_TABLES
*/
global $_TABLES;

/**
*   Global table name prefix
*   @global string $_DB_table_prefix
*/
global $_DB_table_prefix;

$_TABLES['astore_catalog']  = $_DB_table_prefix . 'astore_catalog';
$_TABLES['astore_categories']  = $_DB_table_prefix . 'astore_categories';
$_TABLES['astore_cache']    = $_DB_table_prefix . 'astore_cache';

$_CONF_ASTORE['pi_name']           = 'astore';
$_CONF_ASTORE['pi_version']        = '0.2.0';
$_CONF_ASTORE['gl_version']        = '1.7.0';
$_CONF_ASTORE['pi_url']            = 'http://www.leegarner.com';
$_CONF_ASTORE['pi_display_name']   = 'Amazon Store';

?>
