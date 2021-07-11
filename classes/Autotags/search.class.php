<?php
/**
 * Create a search link to Amazon from an autotag.
 *
 * @copyright   Copyright (c) 2021 Lee Garner
 * @package     astore
 * @version     v0.2.2
 * @since       v0.2.2
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore\Autotags;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
 * Amazon search autotag.
 * @package astore
 */
class search extends \Astore\Autotag
{
    protected $link_url = 'https://www.amazon.com/s?k=%s&language=%s&linkCode=sl2';

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

        if (!isset($this->opts['q'])) {
            return '';
        }

        $url = sprintf($this->link_url, $this->opts['q'], $LANG_LOCALE);
        $url .= $this->getTagForLink('&tag=%s');
        $retval = COM_createLink(
            $this->caption,
            $url,
            array(
                'target' => '_blank',
                'title' => $this->title,
                'class' => 'tooltip',
                'rel' => 'nofollow sponsored noopener',
            )
        );
        $retval = $this->before . $retval . $this->after;
        $retval = '<span ' . $this->getStyle() . '>' . $retval . '</span>';
        return $retval;
    }

}
