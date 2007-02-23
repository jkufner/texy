<?php

/**
 * Texy! universal text -> html converter
 * --------------------------------------
 *
 * This source file is subject to the GNU GPL license.
 *
 * @author     David Grudl aka -dgx- <dave@dgx.cz>
 * @link       http://texy.info/
 * @copyright  Copyright (c) 2004-2007 David Grudl
 * @license    GNU GENERAL PUBLIC LICENSE v2
 * @package    Texy
 * @category   Text
 * @version    $Revision$ $Date$
 */

// security - include texy.php, not this file
if (!defined('TEXY')) die();



/**
 * Html tags module
 */
class TexyHtmlModule extends TexyModule
{
    protected $allow = array('Html', 'HtmlTag', 'HtmlComment');

    public $safeTags = array(
        'a'         => array('href', 'rel', 'title', 'lang'),
        'abbr'      => array('title', 'lang'),
        'acronym'   => array('title', 'lang'),
        'b'         => array('title', 'lang'),
        'br'        => array(),
        'cite'      => array('title', 'lang'),
        'code'      => array('title', 'lang'),
        'dfn'       => array('title', 'lang'),
        'em'        => array('title', 'lang'),
        'i'         => array('title', 'lang'),
        'kbd'       => array('title', 'lang'),
        'q'         => array('cite', 'title', 'lang'),
        'samp'      => array('title', 'lang'),
        'small'     => array('title', 'lang'),
        'span'      => array('title', 'lang'),
        'strong'    => array('title', 'lang'),
        'sub'       => array('title', 'lang'),
        'sup'       => array('title', 'lang'),
        'var'       => array('title', 'lang'),
    );



    public function init()
    {
        $this->texy->registerLinePattern(
            $this,
            'process',
            '#<(/?)([a-z][a-z0-9_:-]*)(/?|\s(?:[\sa-z0-9:-]|=\s*"[^"'.TEXY_MARK.']*"|=\s*\'[^\''.TEXY_MARK.']*\'|=[^>'.TEXY_MARK.']*)*)>|<!--([^'.TEXY_MARK.']*?)-->#is',
            'Html'
        );
    }



    /**
     * Callback function: <tag ...>  | <!-- comment -->
     * @return string
     */
    public function process($parser, $matches)
    {
        $matches[] = NULL;
        list($match, $mClosing, $mTag, $mAttr, $mComment) = $matches;
        //    [1] => /
        //    [2] => tag
        //    [3] => attributes
        //    [4] => /
        //    [5] => comment

        $tx = $this->texy;

        if ($mTag == '') { // html comment
            if (empty($tx->allowed['HtmlComment']))
                return substr($matches[5], 0, 1) === '[' ? $match : '';

            return $tx->mark($match, Texy::CONTENT_NONE);
        }

        if (empty($tx->allowed['HtmlTag'])) return $match;

        $tag = strtolower($mTag);
        if (!isset(Texy::$validTags[$tag])) $tag = $mTag;  // undo lowercase

        // tag & attibutes
        $aTags = $tx->allowedTags; // speed-up
        if (!$aTags) return $match;  // all tags are disabled
        if (is_array($aTags)) {
            if (!isset($aTags[$tag])) return $match; // this element not allowed
            $aAttrs = $aTags[$tag]; // allowed attrs
        } else {
            $aAttrs = NULL; // all attrs are allowed
        }

        $isEmpty = substr($mAttr, -1) === '/';
        $isOpening = $mClosing !== '/';

        if ($isEmpty && !$isOpening)  // error - can't close empty element
            return $match;

        $el = TexyHtml::el($tag);
        if ($aTags === Texy::ALL && $isEmpty) $el->_empty = TRUE; // force empty

        if (!$isOpening) // closing tag? we are finished
            return $el->endMark($tx);

        // process attributes
        if ($isEmpty) $mAttr = substr($mAttr, 0, -1);
        if (is_array($aAttrs)) $aAttrs = array_flip($aAttrs);
        else $aAttrs = NULL;

        preg_match_all(
            '#([a-z0-9:-]+)\s*(?:=\s*(\'[^\']*\'|"[^"]*"|[^\'"\s]+))?()#is',
            $mAttr,
            $matches2,
            PREG_SET_ORDER
        );

        foreach ($matches2 as $m) {
            $key = strtolower($m[1]);
            if ($aAttrs !== NULL && !isset($aAttrs[$key])) continue;

            $val = $m[2];
            if ($val == NULL) $el->$key = TRUE;
            elseif ($val{0} === '\'' || $val{0} === '"') $el->$key = substr($val, 1, -1);
            else $el->$key = $val;
        }


        // apply allowedClasses & allowedStyles
        $modifier = new TexyModifier($tx);

        if (isset($el->class)) {
            $tmp = $tx->_classes; // speed-up
            if (is_array($tmp)) {
                $el->class = explode(' ', $el->class);
                foreach ($el->class as $key => $val)
                    if (!isset($tmp[$val])) unset($el->class[$key]);

                if (!isset($tmp['#' . $el->id])) $el->id = NULL;
            } elseif ($tmp !== Texy::ALL) {
                $el->class = $el->id = NULL;
            }
        }

        if (isset($el->style)) {
            $tmp = $tx->_styles;  // speed-up
            if (is_array($tmp)) {
                $styles = explode(';', $el->style);
                $el->style = NULL;
                foreach ($styles as $value) {
                    $pair = explode(':', $value, 2); $pair[] = '';
                    $prop = strtolower(trim($pair[0]));
                    $value = trim($pair[1]);
                    if ($value !== '' && isset($tmp[$prop]))
                        $el->style[$prop] = $value;
                }
            } elseif ($tmp !== Texy::ALL) {
                $el->style = NULL;
            }
        }

        if ($tag === 'img') {
            if (!isset($el->src)) return $match;
            $tx->summary['images'][] = $el->src;

        } elseif ($tag === 'a') {
            if (!isset($el->href) && !isset($el->name) && !isset($el->id)) return $match;
            if (isset($el->href)) {
                $tx->summary['links'][] = $el->href;
            }
        }

        return $el->startMark($tx);
    }



    public function trustMode($onlyValidTags = TRUE)
    {
        $this->texy->allowedTags = $onlyValidTags ? Texy::$validTags : Texy::ALL;
    }



    public function safeMode($allowSafeTags = TRUE)
    {
        $this->texy->allowedTags = $allowSafeTags ? $this->safeTags : Texy::NONE;
    }

} // TexyHtmlModule
