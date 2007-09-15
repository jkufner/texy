<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */



/**
 * HTML helper
 *
 * usage:
 *       $anchor = TexyHtml::el('a')->href($link)->setText('Texy');
 *       $el->class = 'myclass';
 *
 *       echo $el->startTag(), $el->endTag();
 *
 * @property mixed element's attributes
 */
class TexyHtml extends TexyBase
{
    /** @var string  element's name */
    private $name;

    /** @var TexyHtml parent element */
    private $parent;

    /** @var bool  is element empty? */
    private $isEmpty;

    /** @var array  element's attributes */
    public $attrs = array();

    /** @var array  of TexyHtml | string nodes */
    public $children = array();

    /** @var bool  use XHTML syntax? */
    public static $xhtml = TRUE;

    /** @var array  empty elements */
    public static $emptyElements = array('img'=>1,'hr'=>1,'br'=>1,'input'=>1,'meta'=>1,'area'=>1,
        'base'=>1,'col'=>1,'link'=>1,'param'=>1,'basefont'=>1,'frame'=>1,'isindex'=>1,'wbr'=>1,'embed'=>1);

    /** @var array  %inline; elements; replaced elements + br have value '1' */
    public static $inlineElements = array('ins'=>0,'del'=>0,'tt'=>0,'i'=>0,'b'=>0,'big'=>0,'small'=>0,'em'=>0,
        'strong'=>0,'dfn'=>0,'code'=>0,'samp'=>0,'kbd'=>0,'var'=>0,'cite'=>0,'abbr'=>0,'acronym'=>0,
        'sub'=>0,'sup'=>0,'q'=>0,'span'=>0,'bdo'=>0,'a'=>0,'object'=>1,'img'=>1,'br'=>1,'script'=>1,
        'map'=>0,'input'=>1,'select'=>1,'textarea'=>1,'label'=>0,'button'=>1,
        'u'=>0,'s'=>0,'strike'=>0,'font'=>0,'applet'=>1,'basefont'=>0, // transitional
        'embed'=>1,'wbr'=>0,'nobr'=>0,'canvas'=>1, // proprietary
    );

    /**
     * DTD descriptor
     *   $dtd[element][0] - allowed attributes (as array keys)
     *   $dtd[element][1] - allowed content for an element (content model) (as array keys)
     *                        - array of allowed elements (as keys)
     *                        - FALSE - empty element
     *                        - 0 - special case for ins & del
     * @var array
     * @see TexyHtmlOutputModule::initDTD()
     */
    public static $dtd;

    /** @var array  elements with optional end tag in HTML */
    public static $optionalEnds = array('body'=>1,'head'=>1,'html'=>1,'colgroup'=>1,'dd'=>1,
        'dt'=>1,'li'=>1,'option'=>1,'p'=>1,'tbody'=>1,'td'=>1,'tfoot'=>1,'th'=>1,'thead'=>1,'tr'=>1);

    /** @see http://www.w3.org/TR/xhtml1/prohibitions.html */
    public static $prohibits = array(
        'a' => array('a','button'),
        'img' => array('pre'),
        'object' => array('pre'),
        'big' => array('pre'),
        'small' => array('pre'),
        'sub' => array('pre'),
        'sup' => array('pre'),
        'input' => array('button'),
        'select' => array('button'),
        'textarea' => array('button'),
        'label' => array('button', 'label'),
        'button' => array('button'),
        'form' => array('button', 'form'),
        'fieldset' => array('button'),
        'iframe' => array('button'),
        'isindex' => array('button'),
    );



    /**
     * Static factory
     * @param string element name (or NULL)
     * @param array element's attributes
     * @return TexyHtml
     */
    public static function el($name = NULL, $attrs = NULL)
    {
        $el = new self;

        if ($name !== NULL) {
            $el->setName($name);
        }

        if ($attrs !== NULL) {
            if (!is_array($attrs)) {
                throw new TexyException('Attributes must be array');
            }

            $el->attrs = $attrs;
        }

        return $el;
    }



    /**
     * Changes element's name
     * @param string
     * @return TexyHtml  itself
     */
    final public function setName($name)
    {
        if ($name !== NULL && !is_string($name)) {
            throw new TexyException('Name must be string or NULL');
        }

        $this->name = $name;
        $this->isEmpty = isset(self::$emptyElements[$name]);
        return $this;
    }



    /**
     * Returns element's name
     * @return string
     */
    final public function getName()
    {
        return $this->name;
    }



    /**
     * Is element empty?
     * @param optional setter
     * @return bool
     */
    final public function isEmpty($value = NULL)
    {
        if (is_bool($value)) {
            $this->isEmpty = $value;
        }

        return $this->isEmpty;
    }



    /**
     * Sets element's textual content
     * @param string
     * @return TexyHtml  itself
     */
    final public function setText($text)
    {
        if (is_scalar($text)) {
            $this->children = array($text);
        } elseif ($text !== NULL) {
            throw new TexyException('Content must be scalar');
        }
        return $this;
    }



    /**
     * Gets element's textual content
     * @return string
     */
    final public function getText()
    {
        $s = '';
        foreach ($this->children as $child) {
            if (is_object($child)) return FALSE;
            $s .= $child;
        }
        return $s;
    }



    /**
     * Adds and creates new TexyHtml child
     * @param string  elements's name
     * @param string optional textual content
     * @return TexyHtml
     */
    final public function add($name, $text = NULL)
    {
        $child = new self;
        $child->setName($name);
        if ($text !== NULL) {
            $child->setText($text);
        }
        $this->addChild($child);
        return $child;
    }



    /**
     * Adds new element's child
     * @param TexyHtml|string child node
     * @param mixed index
     * @return TexyHtml  itself
     */
    final public function addChild($child)
    {
        if ($child instanceof TexyHtml) {
            //$child->parent = $this;
        } elseif (!is_string($child)) {
            throw new TexyException('Child node must be scalar or TexyHtml object');
        }

        $this->children[] = $child;
        return $this;
    }



    /**
     * Returns child node
     * @param mixed index
     * @return TexyHtml
     */
    final public function getChild($index)
    {
        return $this->children[$index];
    }



    /**
     * Overloaded setter for element's attribute
     * @param string    property name
     * @param mixed     property value
     * @return void
     */
    final public function __set($name, $value)
    {
        $this->attrs[$name] = $value;
    }



    /**
     * Overloaded getter for element's attribute
     * @param string    property name
     * @return mixed    property value
     */
    final public function &__get($name)
    {
        return $this->attrs[$name];
    }



    /**
     * Overloaded setter for element's attribute
     * @param string attribute name
     * @param array value
     * @return TexyHtml  itself
     */
/*
    final public function __call($m, $args)
    {
        $this->attrs[$m] = $args[0];
        return $this;
    }
*/


    /**
     * Special setter for element's attribute
     * @param string path
     * @param array query
     * @return TexyHtml  itself
     */
    final public function href($path, $params = NULL)
    {
        if ($params) {
            $query = http_build_query($params, NULL, '&');
            if ($query !== '') $path .= '?' . $query;
        }
        $this->attrs['href'] = $path;
        return $this;
    }



    /**
     * Renders element's start tag, content and end tag to internal string representation
     * @param Texy
     * @return string
     */
    final public function toString(Texy $texy)
    {
        $ct = $this->getContentType();
        $s = $texy->protect($this->startTag(), $ct);

        // empty elements are finished now
        if ($this->isEmpty) {
            return $s;
        }

        // add content
        foreach ($this->children as $child) {
            if (is_object($child)) {
                $s .= $child->toString($texy);
            } else {
                $s .= $child;
            }
        }

        // add end tag
        return $s . $texy->protect($this->endTag(), $ct);
    }



    /**
     * Renders to final HTML
     * @param Texy
     * @return string
     */
    final public function toHtml(Texy $texy)
    {
        return $texy->stringToHtml($this->toString($texy));
    }



    /**
     * Renders to final text
     * @param Texy
     * @return string
     */
    final public function toText(Texy $texy)
    {
        return $texy->stringToText($this->toString($texy));
    }



    /**
     * Returns element's start tag
     * @return string
     */
    public function startTag()
    {
        if (!$this->name) {
            return '';
        }

        $s = '<' . $this->name;

        if (is_array($this->attrs)) {
            foreach ($this->attrs as $key => $value)
            {
                // skip NULLs and false boolean attributes
                if ($value === NULL || $value === FALSE) continue;

                // true boolean attribute
                if ($value === TRUE) {
                    // in XHTML must use unminimized form
                    if (self::$xhtml) $s .= ' ' . $key . '="' . $key . '"';
                    // in HTML should use minimized form
                    else $s .= ' ' . $key;
                    continue;

                } elseif (is_array($value)) {

                    // prepare into temporary array
                    $tmp = NULL;
                    foreach ($value as $k => $v) {
                        // skip NULLs & empty string; composite 'style' vs. 'others'
                        if ($v == NULL) continue;

                        if (is_string($k)) $tmp[] = $k . ':' . $v;
                        else $tmp[] = $v;
                    }

                    if (!$tmp) continue;
                    $value = implode($key === 'style' ? ';' : ' ', $tmp);

                } elseif ($key === 'href' && substr($value, 0, 7) === 'mailto:') {
                    // email-obfuscate hack
                    $tmp = '';
                    for ($i = 0; $i<strlen($value); $i++) $tmp .= '&#' . ord($value[$i]) . ';'; // WARNING: no utf support
                    $s .= ' href="' . $tmp . '"';
                    continue;
                }

                // add new attribute
                $value = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $value);
                $s .= ' ' . $key . '="' . Texy::freezeSpaces($value) . '"';
            }
        }

        // finish start tag
        if (self::$xhtml && $this->isEmpty) return $s . ' />';
        return $s . '>';
    }



    /**
     * Returns element's end tag
     * @return string
     */
    public function endTag()
    {
        if ($this->name && !$this->isEmpty) {
            return '</' . $this->name . '>';
        }
        return '';
    }



    /**
     * Clones all children too
     */
    final public function __clone()
    {
        foreach ($this->children as $key => $value) {
            if (is_object($value)) {
                $this->children[$key] = clone $value;
            }
        }
    }



    /**
     * @return int
     */
    final public function getContentType()
    {
        if (!isset(self::$inlineElements[$this->name])) return Texy::CONTENT_BLOCK;

        return self::$inlineElements[$this->name] ? Texy::CONTENT_REPLACED : Texy::CONTENT_MARKUP;
    }



    /**
     * @return void
     */
    final public function validateAttrs()
    {
        if (isset(self::$dtd[$this->name])) {
            $dtd = self::$dtd[$this->name][0];
            if (is_array($dtd)) {
                foreach ($this->attrs as $attr => $foo) {
                    if (!isset($dtd[$attr])) unset($this->attrs[$attr]);
                }
            }
        }
    }



    public function validateChild($child)
    {
        if (isset(self::$dtd[$this->name])) {
            if ($child instanceof TexyHtml) $child = $child->name;
            return isset(self::$dtd[$this->name][1][$child]);
        } else {
            return TRUE; // unknown element
        }
    }




    /**
     * Parses text as single line
     * @param Texy
     * @param string
     * @return void
     */
    final public function parseLine($texy, $s)
    {
        // TODO!
        // special escape sequences
        $s = str_replace(array('\)', '\*'), array('&#x29;', '&#x2A;'), $s);

        $parser = new TexyLineParser($texy, $this);
        $parser->parse($s);
    }



    /**
     * Parses text as block
     * @param Texy
     * @param string
     * @param bool
     * @return void
     */
    final public function parseBlock($texy, $s, $indented = FALSE)
    {
        $parser = new TexyBlockParser($texy, $this, $indented);
        $parser->parse($s);
    }



    /**
     * Initializes TexyHtml::$dtd array
     * @param bool
     * @return void
     */
    public static function initDTD($strict)
    {
        TexyHtml_initDTD($strict);
    }

}
