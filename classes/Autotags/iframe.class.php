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
class iframe extends \Astore\Autotag
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
        if (!empty($this->opts['caption'])) {
            $this->caption = $this->opts['caption'];
        }

        // The tracking_id is required in the iframe in order for the
        // product to be rendered.
        $url_opts = array(
            'ServiceVersion' => '20070822',
            'OneJS' => 1,
            'Operation' => 'GetAdHtml',
            'MarketPlace' => 'US',
            'source' => 'ss',
            'ref' => 'as_ss_li_til',
            'ad_type' => 'product_link',
            'tracking_id' => $this->getTagForLink(),
            'language' => $LANG_LOCALE,
            'marketplace' => 'amazon' ,
            'region' => 'US',
            'placement' => $asin,
            'asins' => $asin,
            'linkId' => uniqid(),
            'show_border' => 'true',
            'link_opens_in_new_window' => 'true',
        );
        $retval = '<iframe style="width:120px;height:240px;" marginwidth="0" ' .
            'marginheight="0" scrolling="no" frameborder="0" src="//ws-na.amazon-adsystem.com/widgets/q?' . http_build_query($url_opts) . '"></iframe>';
        if (!empty($this->disc_text)) {
                $retval .= '<br />' . $this->disc_text;
        }
        return $this->before . $retval . $this->after;
    }

}
