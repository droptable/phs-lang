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

$cmp->add_source(new FileSource(__DIR__ . '/../lib/std.phs'));
$cmp->add_source(new FileSource(__DIR__ . '/../test/test.phs'));

$now = microtime();
$cmp->compile();
$end = microtime() - $now;

print "\ndone in {$end}s\n";
print "\nscope:\n------\n\n";
$ctx->get_root()->debug();






 
