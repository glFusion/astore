<?php
/**
 * Create text or image links from autotags.
 *
 * @copyright   Copyright (c) 2009-2020 Lee Garner
 * @package     astore
 * @version     v1.1.0
 * @since       v0.7.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Astore;

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own!');
}


/**
 * Amazon base autotag class.
 * @package astore
 */
class Autotag
{
    /** Amazon associate ID.
     * @var string */
    protected $assoc_id = '';

    /** Main product link on Amazon.
     * @var string */
    protected $link_url = 'https://www.amazon.com/gp/product/%1$s/%2$slanguage=%3$s';

    /** Disclaimer text, from the plugin configuration.
     * @var string */
    protected $disc_text = '';

    /** Disclaimer placement, from the plugin configuration.
     * @var string */
    protected $disc_placement = 'title';

    /** Popup title to show on hover.
     * @var string */
    protected $title = 'Go to Amazon';

    /** Autotag options, array created from autotag `parm2` variable.
     * @var array */
    protected $opts = array();  // broken-down autotag options

    /** Caption to be displayed.
     * @var string */
    protected $caption = '';

    /** Actual autotag contents.
     * @var array */
    protected $autotag = NULL;

    /** HTML to include before the link.
     * @var string */
    protected $before = '';

    /** HTML to include after the link.
     * @var string */
    protected $after = '';


    /**
     * Set up private variables
     */
    public function __construct()
    {
        global $LANG_LOCALE, $_CONF_ASTORE;

        // get from config
        $this->disc_text = $_CONF_ASTORE['disclaimer'];
        $this->assoc_id = $_CONF_ASTORE['aws_assoc_id'];

        if (!empty($disc_text)) {
            switch ($disc_placement) {
            case 'after':
                $this->after = ' <span style="font-size:.8em;">' . $disc_text . '</span>';
                break;
            case 'before':
                $this->before = '<span style="font-size:.8em;">' . $disc_text . '</span> ';
                break;
            case 'title':
                $this->title = $disc_text;
                break;
            }
        }
    }


    /**
     * Get the associate ID tag for a link.
     * Returns nothing if the header or user should not be shown the tag.
     *
     * @param   string  $tagstr     Format tag to use with sprintf()
     * @return  string      Formatted string including associate ID
     */
    protected function getTagForLink($tagstr='%s')
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
            return sprintf($tagstr, $this->assoc_id);
        }
    }


    /**
     * Set the autotag contents and parse into components.
     *
     * @param   array   $autotag    Autotag array from glFusion
     * @return  object  $this
     */
    public function withAutotag($autotag)
    {
        $this->autotag = $autotag;

        $skip = 0;
        $params = explode(' ', $this->autotag['parm2']);
        foreach ($params as $param) {
            if (strstr($param, ':')) {
                list($key, $value) = explode(':', $param);
                $this->opts[$key] = $value;
                $skip++;
            }
        }
        if ($skip != 0 && count($params) > $skip) {
            for ($i = 0; $i < $skip; $i++) {
                array_shift ($params);
            }
            $this->caption = trim (implode (' ', $params));
        }
        return $this;
    }

}
