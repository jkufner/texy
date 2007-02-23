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
 * HTML helper
 */
class TexyHtml
{
    /** @var string element's name */
    public $_name;

    /** @var bool is element empty? */
    public $_empty;

    /* element's attributes are not explicitly declared */


    /**
     * TexyHtml element's factory
     * @param string element name (or NULL)
     * @param array  optional attributes list
     * @return TexyHtml
     */
    static public function el($name=NULL, $attrs=NULL)
    {
        return new self($name, $attrs);
    }


    private function __construct($name, $attrs)
    {
        if (is_array($attrs)) {
           foreach ($attrs as $key => $value) $this->$key = $value;
        }

        $this->_name = $name;
        $this->_empty = isset(Texy::$emptyTags[$name]);
    }


    /**
     * Changes element's name
     * @param string
     * @return TexyHtml self
     */
    public function setElement($name)
    {
        $this->_name = $name;
        $this->_empty = isset(Texy::$emptyTags[$name]);
        return $this;
    }


    /**
     * Overloaded setter for element's attribute
     * @param string function name
     * @param array function arguments
     * @return TexyHtml self
     */
    public function __call($m, $args)
    {
        $this->$m = $args[0];
        return $this;
    }


    /**
     * Returns element's start tag
     * @return string
     */
    public function startTag()
    {
        if (!$this->_name) return '';

        $s = '<' . $this->_name;

        // reserved properties
    	static $res = array('_name'=>1, '_empty'=>1,);

        // use array_change_key_case($this, CASE_LOWER) ?
        // for each attribute...
        foreach ($this as $key => $value)
        {
            // skip private properties
            if (isset($res[$key])) continue;

            // skip NULLs and false boolean attributes
            if ($value === NULL || $value === FALSE) continue;

            // true boolean attribute
            if ($value === TRUE) {
                // in XHTML must use unminimized form
                if (Texy::$xhtml) $s .= ' ' . $key . '="' . $key . '"';
                // in HTML should use minimized form
                else $s .= ' ' . $key;
                continue;

            } elseif (is_array($value)) {

                // prepare into temporary array
                $tmp = NULL;
                // use array_change_key_case($value, CASE_LOWER) ?
                foreach ($value as $k => $v) {
                    // skip NULLs & empty string; composite 'style' vs. 'others'
                    if ($v == NULL) continue;

                    if (is_string($k)) $tmp[] = $k . ':' . $v;
                    else $tmp[] = $v;
                }

                if (!$tmp) continue;
                $value = implode($key === 'style' ? ';' : ' ', $tmp);
            }
            // add new attribute
            //$s .= ' ' . $key . '="' . Texy::freezeSpaces(htmlSpecialChars($value, ENT_COMPAT)) . '"';
            // BACK-COMPATIBILITY-HACK !!!
            $s .= ' ' . $key . '="' . Texy::freezeSpaces(
                preg_replace('~&amp;([a-zA-Z0-9]+|#x[0-9a-fA-F]+|#[0-9]+);~', '&$1;', htmlSpecialChars($value, ENT_COMPAT))) . '"';
        }

        // finish start tag
        if (Texy::$xhtml && $this->_empty) return $s . ' />';
        return $s . '>';
    }


    /**
     * Returns element's end tag
     * @return string
     */
    public function endTag()
    {
        if ($this->_name && !$this->_empty) return '</' . $this->_name . '>';
        return '';
    }


    /**
     * Returns element's start tag as Texy mark
     * @return string
     */
    public function startMark($texy)
    {
        $s = $this->startTag();
        if ($s === '') return '';
        return $texy->mark($s, $this->getContentType());
    }


    /**
     * Returns element's end tag as Texy mark
     * @return string
     */
    public function endMark($texy)
    {
        $s = $this->endTag();
        if ($s === '') return '';
        return $texy->mark($s, $this->getContentType());
    }


    /**
     * @return int
     */
    public function getContentType()
    {
        if (isset(Texy::$inlineCont[$this->_name])) return Texy::CONTENT_INLINE;
        if (isset(Texy::$inlineTags[$this->_name])) return Texy::CONTENT_NONE;

        return Texy::CONTENT_BLOCK;
    }


}
