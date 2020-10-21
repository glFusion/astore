<?php
/**
 * Handle the headline autotag for the Shop plugin.
 * Based on the glFusion headline autotag.
 *
 * @copyright   Copyright (c) 2009-2020 Lee Garner
 * @package     shop
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
 * Headline autotag class.
 * @package shop
 */
class azlink
{
    /**
     * Parse the autotag and render the output.
     *
     * @param   string  $p1         First option after the tag name
     * @param   array   $opts       Array of options
     * @param   string  $fulltag    Full autotag string
     * @return  string      Replacement HTML, if applicable.
     */
    public function parse($p1, $opts, $fulltag)
    {
        global $LANG_LOCALE, $_CONF_ASTORE;

        // TODO: config items
        $disc_text = 'Paid Amazon Link';
        $disc_placement = 'title';
        // get from config
        $assoc_id = $_CONF_ASTORE['aws_assoc_id'];

        $type = 'text';
        $caption = '';
        $skip = 0;
        foreach ($opts as $key=>$val) {
            $val = strtolower($val);
            switch ($key) {
            case 'type':
                if (in_array($val, array('text', 'image', 'both', 'short'))) {
                    $$key = $val;
                }
                $skip++;
                break;
            /*default:
                $$key = $val;
                $skip++;
                break;*/
             }
        }

        $caption = array();
        if ($skip > 0 && count($opts) > $skip) {
            for ($i = 0; $i < $skip; $i++) {
                array_shift($opts);
            }
        }
        foreach ($opts as $key=>$val) {
            $caption[] = $key;
        }
        $caption = trim(implode (' ', $caption));

        $url = 'https://www.amazon.com/gp/product/%1$s/%2$slanguage=%3$s';
        $title = 'Go to Amazon';
        $after = '';
        $before = '';
        if (!empty($disc_text)) {
            switch ($disc_placement) {
            case 'after':
                $after = ' <span style="font-size:.8em;">' . $disc_text . '</span>';
                break;
            case 'before':
                $before = '<span style="font-size:.8em;">' . $disc_text . '</span> ';
                break;
            case 'title':
                $title = $disc_text;
                break;
            }
        }

        switch ($type) {
        case 'short':
            // short link using amzn.to. $p1 is the short code, not the bid.
            $retval = COM_createLink(
                $caption,
                'https://amzn.to/' . $p1,
                array(
                    'target' => '_blank',
                    'title' => $title,
                    'class' => 'tooltip',
                )
            );
            break;

        case 'text':
            $retval = COM_createLink(
                $caption,
                sprintf($url, $p1, self::getTagForLink('tag=%s&'), $LANG_LOCALE),
                array(
                    'target' => '_blank',
                    'title' => $title,
                    'class' => 'tooltip',
                )
            );
            break;

        case 'image':
            $img_url = 'https://ws-na.amazon-adsystem.com/widgets/q?_encoding=UTF8&ASIN=%1$s&Format=_SL160_&ID=AsinImage&MarketPlace=US&ServiceVersion=20070822&WS=1%2$s&language=%3$s';
            $bug_url = 'https://ir-na.amazon-adsystem.com/e/ir?t=%2$s&language=%3$s&l=li2&o=1&a=%1$s';
            $retval = COM_createLink(
                COM_createImage(
                    sprintf($img_url, $p1, self::getTagForLink('&tag=%s'), $LANG_LOCALE),
                    '',
                    array(
                        'border' => 0,
                    )
                ),
                sprintf($url, $p1, $assoc_id, $LANG_LOCALE),
                array(
                    'target' => '_blank',
                    'title' => $title,
                    'class' => 'tooltip',
                )
            );
            $retval .= COM_createImage(
                sprintf(
                    $bug_url, $p1, self::getTagForLink($assoc_id, $LANG_LOCALE),
                    '',
                    array(
                        'width' => '1',
                        'height' => '1',
                        'border' => '0',
                        'style' => 'border:none !important; margin:0px !important;',
                    )
                )
            );
            break;

        case 'both':
            $iframe_url = '//ws-na.amazon-adsystem.com/widgets/q?ServiceVersion=20070822&OneJS=1&Operation=GetAdHtml&MarketPlace=US&source=ss&ad_type=product_link%2$s&language=%3$s&marketplace=amazon&region=US&placement=%1$%&asins=%1$s&linkId=952a556a30e19edc6b1fc499ab4d2cac&show_border=true&link_opens_in_new_window=true';
            $retval = '<iframe style="width:120px;height:240px;" marginwidth="0" marginheight="0" ' .
                'scrolling="no" frameborder="0" src="' .
                sprintf($iframe_url, $p1, self::getTagForLink('&tracking_id=%s'), $LANG_LOCALE) . '"></iframe>';
            if (!empty($disc_text)) {
                $retval .= '<br />' . $disc_text;
            }
            break;
        }

        return $before . $retval . $after;
    }


    /**
     * Get the associate ID tag for a link.
     * Returns nothing if the header or user should not be shown the tag.
     *
     * @param   string  $tagstr     Format tag to use with sprintf()
     * @return  string      Formatted string including associate ID
     */
    private static function getTagForLink($tagstr='%s')
    {
        global $_CONF_ASTORE;

        if (
            (
                $_CONF_ASTORE['notag_header'] != '' &&
                isset($_SERVER['HTTP_' . strtoupper($_CONF_ASTORE['notag_header'])])
            ) ||
            $_CONF_ASTORE['notag_admins'] && plugin_ismoderator_astore()
        ) {
            return '';
        } else {
            return sprintf($tagstr, $_CONF_ASTORE['aws_assoc_id']);
        }
    }

}

?>
