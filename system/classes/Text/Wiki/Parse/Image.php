<?php

namespace Geeklog\Text\Wiki;

use Geeklog\Text\Wiki;

/**
 * Parses for image placement.
 *
 * @category Text
 * @package  Text_Wiki
 * @author   Paul M. Jones <pmjones@php.net>
 * @license  LGPL
 * @version  $Id: Image.php 195859 2005-09-12 11:34:44Z toggg $
 */

/**
 * Parses for image placement.
 *
 * @category Text
 * @package  Text_Wiki
 * @author   Paul M. Jones <pmjones@php.net>
 */
class Text_Wiki_Parse_Image extends Text_Wiki_Parse
{
    /**
     * URL schemes recognized by this rule.
     *
     * @var array
     */
    var $conf = array(
        'schemes'     => 'http|https|ftp|gopher|news',
        'host_regexp' => '(?:[^.\s/"\'<\\\#delim#\ca-\cz]+\.)*[a-z](?:[-a-z0-9]*[a-z0-9])?\.?',
        'path_regexp' => '(?:/[^\s"<\\\#delim#\ca-\cz]*)?',
    );

    /**
     * The regular expression used to find source text matching this
     * rule.
     *
     * @var string
     */
    public $regex = '/(\[\[image\s+)(.+?)(\]\])/i';

    /**
     * The regular expressions used to check ecternal urls
     *
     * @var string
     * @see    parse()
     */
    public $url = '';

    /**
     * Constructor.
     * We override the constructor to build up the url regex from config
     *
     * @param Wiki $obj the base conversion handler
     */
    public function __construct($obj)
    {
        $default = $this->conf;
        parent::__construct($obj);

        // convert the list of recognized schemes to a regex OR,
        $schemes = $this->getConf('schemes', $default['schemes']);
        $this->url = str_replace('#delim#', $this->wiki->delim,
            '#(?:' . (is_array($schemes) ? implode('|', $schemes) : $schemes) . ')://'
            . $this->getConf('host_regexp', $default['host_regexp'])
            . $this->getConf('path_regexp', $default['path_regexp']) . '#'
        );
    }

    /**
     * Generates a token entry for the matched text.  Token options are:
     * 'src' => The image source, typically a relative path name.
     * 'opts' => Any macro options following the source.
     *
     * @param  array &$matches The array of matches from parse().
     * @return string A delimited token number to be used as a placeholder in
     *                        the source text.
     */
    public function process(&$matches)
    {
        $pos = strpos($matches[2], ' ');

        if ($pos === false) {
            $options = array(
                'src'  => $matches[2],
                'attr' => array());
        } else {
            // everything after the space is attribute arguments
            $options = array(
                'src'  => substr($matches[2], 0, $pos),
                'attr' => $this->getAttrs(substr($matches[2], $pos + 1)),
            );

            // check the scheme case of external link
            if (array_key_exists('link', $options['attr'])) {
                // external url ?
                if (($pos = strpos($options['attr']['link'], '://')) !== false) {
                    if (!preg_match($this->url, $options['attr']['link'])) {
                        return $matches[0];
                    }
                } elseif (in_array('Wikilink', $this->wiki->disable)) {
                    return $matches[0]; // Wikilink disabled
                }
            }
        }

        return $this->wiki->addToken($this->rule, $options);
    }
}