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

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
 * Amazon link autotag
 * @package astore
 */
class image extends \Astore\Autotag
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

        if (isset($this->opts['align'])) {
            $align = $this->opts['align'];
        } else {
            $align = 'none';
        }
        $img_url = 'https://ws-na.amazon-adsystem.com/widgets/q?';
        $img_opts = array(
            'encoding' => 'UTF8',
            'ASIN' => $asin,
            'Format' => '_SL160_',
            'ID' => 'AsinImage',
            'MarketPlace' => 'US',
            'ServiceVersion' => '20070822',
            'WS' => '1',
            'language' => $LANG_LOCALE,
        );
        $bug_url = 'https://ir-na.amazon-adsystem.com/e/ir?';
        $bug_opts = array(
            'language' => $LANG_LOCALE,
            'l' => 'li2',
            'o' => '1',
            'a' => $asin,
        );
        $tag = $this->getTagForLink('&tag=%s');
        if (!empty($tag)) {
            // if not omitted due to admin access, testing, etc.
            $bug_opts['tag'] = $tag;
            $img_opts['tag'] = $tag;
        }
        $retval = COM_createLink(
            COM_createImage(
                $img_url . http_build_query($img_opts),
                'Amazon Product Link',
                array(
                    'border' => 0,
                    'align' => $align,
                )
            ),
            sprintf($this->link_url, $asin, $this->getTagForLink('tag=%s&'), $LANG_LOCALE),
            array(
                'target' => '_blank',
                'title' => $this->title,
                'class' => 'tooltip',
            )
        );
        // Add the web bug image
        $retval .= COM_createImage(
            $bug_url . http_build_query($bug_opts),
            '',
            array(
                'width' => '1',
                'height' => '1',
                'border' => '0',
                'style' => 'border:none !important; margin:0px !important;',
            )
        );
        return $this->before . $retval . $this->after;
    }

}
