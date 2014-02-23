<?php

$SRC = realpath(__DIR__ . '/../src');

require "$SRC/source.php";
require "$SRC/context.php";
require "$SRC/compiler.php";

use phs\Context;
use phs\Compiler;

use phs\TextSource;
use phs\FileSource;

$ctx = new Context;
$cmp = new Compiler($ctx);

$cmp->add(new FileSource(__DIR__ . '/test.phs'));
$cmp->compile();



 
