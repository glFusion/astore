<?php
/**
 * Create a link to a store item from an autotag.
 *
 * @copyright   Copyright (c) 2021 Lee Garner
 * @package     astore
 * @version     v0.2.1
 * @since       v0.2.1
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore\Autotags;
use Astore\Item as asItem;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
 * Link to an Astore item.
 * @package astore
 */
class item extends \Astore\Autotag
{
    /**
     * Parse the autotag and render the output.
     *
     * @return  string      Replacement HTML, if applicable.
     */
    public function parse()
    {
        global $LANG_LOCALE, $_CONF_ASTORE;

        if (!isset($this->opts['asin'])) {
            return '';
        }
        $asin = $this->opts['asin'];

        $Item = new asItem($asin);
        if ($Item->Data()) {        // Make sure some valid data was returned
            $retval = COM_createLink(
                $this->caption,
                $Item->DetailPageURL(),
                array('target' => '_blank')
            );
        }
        return $this->before . $retval . $this->after;
    }

}
