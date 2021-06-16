<?php
/**
 * Create text or image links from autotags.
 *
 * @copyright   Copyright (c) 2021 Lee Garner
 * @package     astore
 * @version     v1.1.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore\Autotags;
use Astore\Item;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
 * Amazon link autotag
 * @package astore
 */
class text extends \Astore\Autotag
{
    /**
     * Parse the autotag and render the output.
     *
     * @param   string  $p1         First option after the tag name
     * @param   array   $opts       Array of options
     * @param   string  $fulltag    Full autotag string
     * @return  string      Replacement HTML, if applicable.
     */
    public function parse()
    {
        global $LANG_LOCALE, $_CONF_ASTORE;

        if (!isset($this->opts['asin'])) {
            return '';
        }
        $asin = $this->opts['asin'];
        if (!empty($this->caption)) {
            $caption = $this->caption;
        } else {
            $caption = 'Click for Amazon';
        }

        $retval = COM_createLink(
            $caption,
            sprintf($this->link_url, $asin, $this->getTagForLink('tag=%s&'), $LANG_LOCALE),
            array(
                'target' => '_blank',
                'title' => $this->title,
                'class' => 'tooltip',
            )
        );
        return $this->before . $retval . $this->after;
    }

}
