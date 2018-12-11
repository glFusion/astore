<?php
/**
 *   Table definitions for the Astore plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2018 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

global $_TABLES;

$_SQL['astore_catalog'] = "CREATE TABLE {$_TABLES['astore_catalog']} (
  `asin` varchar(32) NOT NULL,
  `title` text,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`asin`),
  KEY `ts` (`ts`,`asin`)
) ENGINE=MyISAM";

$_SQL['astore_cache'] = "CREATE TABLE {$_TABLES['astore_cache']} (
  `asin` varchar(128) NOT NULL,
  `type` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `data` text,
  `exp` int(11) unsigned NOT NULL,
  PRIMARY KEY (`asin`),
  KEY `exp` (`exp`)
) ENGINE=MyISAM";

$ASTORE_UPGRADE = array(
    '0.1.2' => array(
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD title TEXT AFTER asin",
    ),
    '0.2.0' => array(
        "ALTER TABLE {$_TABLES['astore_catalog']} DROP KEY `ts`",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD KEY `ts` (`ts`, `asin`)",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1'",
    ),
);

?>
