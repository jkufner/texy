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



// for PHP 4 backward compatibility
define('TEXY_HEADING_FIXED',  TexyHeadingModule::FIXED);
define('TEXY_HEADING_DYNAMIC',  TexyHeadingModule::DYNAMIC);


/**
 * Heading module
 */
final class TexyHeadingModule extends TexyModule
{
    const
        DYNAMIC = 1,  // auto-leveling
        FIXED = 2;  // fixed-leveling

    /** @var string  textual content of first heading */
    public $title;

    /** @var array  generated Table of Contents */
    public $TOC;

    /** @var bool  autogenerate ID */
    public $generateID = FALSE;

    /** @var string  prefix for autogenerated ID */
    public $idPrefix = 'toc-';

    /** @var int  level of top heading, 1..6 */
    public $top = 1;

    /** @var int  balancing mode */
    public $balancing = TexyHeadingModule::DYNAMIC;

    /** @var array  when $balancing = TexyHeadingModule::FIXED */
    public $levels = array(
        '#' => 0,  //  #  -->  $levels['#'] + $top = 0 + 1 = 1  --> <h1> ... </h1>
        '*' => 1,
        '=' => 2,
        '-' => 3,
    );

    /** @var array  used ID's */
    private $usedID;



    public function __construct($texy)
    {
        $this->texy = $texy;

        $texy->addHandler('heading', array($this, 'solve'));
        $texy->addHandler('beforeParse', array($this, 'beforeParse'));
        $texy->addHandler('afterParse', array($this, 'afterParse'));

        $texy->registerBlockPattern(
            array($this, 'patternUnderline'),
            '#^(\S.*)'.TEXY_MODIFIER_H.'?\n'
          . '(\#{3,}|\*{3,}|={3,}|-{3,})$#mU',
            'heading/underlined'
        );

        $texy->registerBlockPattern(
            array($this, 'patternSurround'),
            '#^(\#{2,}+|={2,}+)(.+)'.TEXY_MODIFIER_H.'?()$#mU',
            'heading/surrounded'
        );
    }



    public function beforeParse()
    {
        $this->title = NULL;
        $this->usedID = array();
        $this->TOC = array();
    }



    /**
     * @param Texy
     * @param TexyHtml
     * @param bool
     * @return void
     */
    public function afterParse($texy, $DOM, $isSingleLine)
    {
        if ($isSingleLine || $this->balancing === self::FIXED) return;

        $top = $this->top;
        $map = array();
        $min = 100;
        foreach ($this->TOC as $item)
        {
            $level = $item['level'];
            if ($item['surrounded']) {
                $min = min($level, $min);
                $top = $this->top - $min;
            } else {
                $map[$level] = $level;
            }
        }

        asort($map);
        $map = array_flip(array_values($map));

        foreach ($this->TOC as $key => $item)
        {
            $level = $item['level'];
            $level = $item['surrounded'] ? $level + $top : $map[$level] + $this->top;
            $item['el']->setName('h' . min(6, max(1, $level)));
            $this->TOC[$key]['level'] = $level;
        }
    }



    /**
     * Callback for underlined heading
     *
     *  Heading .(title)[class]{style}>
     *  -------------------------------
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternUnderline($parser, $matches)
    {
        list(, $mContent, $mMod, $mLine) = $matches;
        //  $matches:
        //    [1] => ...
        //    [2] => .(title)[class]{style}<>
        //    [3] => ...

        $mod = new TexyModifier($mMod);
        $level = $this->levels[$mLine[0]];
        return $this->texy->invokeAroundHandlers('heading', $parser, array($level, $mContent, $mod, FALSE));
    }



    /**
     * Callback for surrounded heading
     *
     *   ### Heading .(title)[class]{style}>
     *
     * @param TexyBlockParser
     * @param array      regexp matches
     * @param string     pattern name
     * @return TexyHtml|string|FALSE
     */
    public function patternSurround($parser, $matches)
    {
        list(, $mLine, $mContent, $mMod) = $matches;
        //    [1] => ###
        //    [2] => ...
        //    [3] => .(title)[class]{style}<>

        $mod = new TexyModifier($mMod);
        $level = 7 - min(7, max(2, strlen($mLine)));
        $mContent = rtrim($mContent, $mLine[0] . ' ');
        return $this->texy->invokeAroundHandlers('heading', $parser, array($level, $mContent, $mod, TRUE));
    }



    /**
     * Finish invocation
     *
     * @param TexyHandlerInvocation  handler invocation
     * @param int  0..5
     * @param string
     * @param TexyModifier
     * @param bool
     * @return TexyHtml
     */
    public function solve($invocation, $level, $content, $mod, $isSurrounded)
    {
        $tx = $this->texy;
        // as fixed balancing, for block/texysource & correct decorating
        $el = TexyHtml::el('h' . min(6, max(1, $level + $this->top)));
        $mod->decorate($tx, $el);

        $el->parseLine($tx, trim($content));

        // Table of Contents
        $title = NULL;
        if ($this->generateID && empty($el->attrs['id'])) {
            $title = trim($el->toText($tx));
            $id = $this->idPrefix . Texy::webalize($title);
            $counter = '';
            if (isset($this->usedID[$id . $counter])) {
                $counter = 2;
                while (isset($this->usedID[$id . '-' . $counter])) $counter++;
                $id .= '-' . $counter;
            }
            $this->usedID[$id] = TRUE;
            $el->attrs['id'] = $id;
        }

        // document title
        if ($this->title === NULL) {
            if ($title === NULL) $title = trim($el->toText($tx));
            $this->title = $title;
        }

        if ($invocation->getParser()->getLevel() > TexyBlockParser::SEPARATE) {
            $this->TOC[] = array(
                'el' => $el,
                'level' => $level,
                'title' => $title,
                'surrounded' => $isSurrounded,
            );
        }

        return $el;
    }

}
