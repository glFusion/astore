<?php
/**
*   Table definitions for the Astore plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2017 Lee Garner <lee@leegarner.com>
*   @package    astore
*   @version    0.1.0
*   @license    http://opensource.org/licenses/gpl-2.0.php
*               GNU Public License v2 or later
*   @filesource
*/

global $_TABLES;

$_SQL['astore_cache'] = "CREATE TABLE {$_TABLES['astore_cache']} (
  `asin` varchar(128) NOT NULL,
  `type` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `data` text,
  `exp` int(11) unsigned NOT NULL,
  PRIMARY KEY (`asin`),
  KEY `exp` (`exp`)
) ENGINE=MyISAM";

$_SQL['astore_catalog'] = "CREATE TABLE {$_TABLES['astore_catalog']} (
  `asin` varchar(32) NOT NULL,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`asin`),
  KEY `ts` (`ts`)
) ENGINE=MyISAM";

?>
