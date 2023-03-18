<?php
/**
 * Table definitions for the Astore plugin.
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2017-2023 Lee Garner <lee@leegarner.com>
 * @package     astore
 * @version     v0.2.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */

global $_TABLES;

$_SQL = array();
$_SQL['astore_catalog'] = "CREATE TABLE {$_TABLES['astore_catalog']} (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `asin` varchar(32) NOT NULL,
  `cat_id` int(11) unsigned NOT NULL DEFAULT '1',
  `title` text,
  `url` text,
  `editorial` text,
  `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_asin` (`asin`),
  KEY `idx_cat` (`cat_id`),
  KEY `idx_ts` (`ts`,`asin`)
) ENGINE=MyISAM";

$_SQL['astore_categories'] = "CREATE TABLE {$_TABLES['astore_categories']} (
  `cat_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cat_name` varchar(40) NOT NULL DEFAULT '',
  `orderby` int(3) NOT NULL DEFAULT '9999',
  PRIMARY KEY (`cat_id`),
  KEY `idx_orderby` (`orderby`)
) ENGINE=MyISAM;
INSERT IGNORE INTO {$_TABLES['astore_categories']}
    VALUES (1, 'General', 10)";

$ASTORE_UPGRADE = array(
    '0.1.2' => array(
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD title TEXT AFTER asin",
    ),
    '0.2.0' => array(
        "ALTER TABLE {$_TABLES['astore_catalog']} DROP KEY IF EXISTS `ts`",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD KEY `idx_ts` (`ts`, `asin`)",
        "ALTER TABLE {$_TABLES['astore_catalog']} DROP PRIMARY KEY",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD `id` int(11) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD `cat_id` int(11) unsigned NOT NULL DEFAULT '1' AFTER `asin`",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD `title` text AFTER `cat_id`",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD UNIQUE KEY `idx_asin` (`asin`)",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD `url` text AFTER `title`",
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD `enabled` tinyint(1) unsigned NOT NULL DEFAULT '1'",
        "CREATE TABLE {$_TABLES['astore_categories']} (
          `cat_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `cat_name` varchar(40) NOT NULL DEFAULT '',
          `orderby` int(3) NOT NULL DEFAULT '9999',
          PRIMARY KEY (`cat_id`),
          KEY `idx_orderby` (`orderby`)
        ) ENGINE=MyISAM",
        "ALTER TABLE {$_TABLES['astore_cache']} ADD `ts` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `exp`",
        "INSERT IGNORE INTO {$_TABLES['astore_categories']}
            VALUES (1, 'General', 10)",
    ),
    '0.2.1' => array(
        "ALTER TABLE {$_TABLES['astore_catalog']} ADD `editorial` text AFTER `url`",
    ),
    '0.3.0' => array(
        "DROP TABLE IF EXISTS {$_TABLES['astore_cache']}",
    ),
);
