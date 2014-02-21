#!/usr/bin/php
<?php

require '../src/context.php';
require '../src/lexer.php';
require '../src/parser.php';

$ctx = new phs\Context;
$psr = new phs\Parser($ctx);
$ast = $psr->parse_file('./test.phs', 'test');

#var_dump($ast);
