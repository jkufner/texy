<?php

/**
 * This file is part of the Texy! (http://texy.info)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Texy;


/**
 * Texy parser base class.
 *
 * @author     David Grudl
 */
class Parser extends Object
{
	/** @var Texy */
	protected $texy;

	/** @var HtmlElement */
	protected $element;

	/** @var array */
	public $patterns;


	/**
	 * @return Texy
	 */
	public function getTexy()
	{
		return $this->texy;
	}

}

