<?php

/**
 * Test: Headings.
 */

require __DIR__ . '/../bootstrap.php';


$texy = new Texy;
$texy->htmlOutputModule->lineWrap = 180;

Assert::matchFile(
	__DIR__ . '/expected/headings1.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/headings1.texy'))
);

Assert::matchFile(
	__DIR__ . '/expected/headings2.html',
	$texy->process(file_get_contents(__DIR__ . '/sources/headings2.texy'))
);
