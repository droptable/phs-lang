#!/usr/bin/env php
<?php

$DIR = realpath(__DIR__ . '/../src');
$DIR = rtrim($DIR, '\\/');

$TPL = <<<'END'
<?php

namespace phs;

/**
 * This is an automatically GENERATED file!
 * See: bin/ast_gen.php
 */

require_once 'ast/node.php';
require_once 'ast/decl.php';
require_once 'ast/stmt.php';
require_once 'ast/expr.php';
END;

foreach (glob("$DIR/ast/*.php") as $path) {
  $file = basename($path);
  
  if ($file === 'node.php' ||
      $file === 'decl.php' ||
      $file === 'stmt.php' ||
      $file === 'expr.php')
    continue;
  
  $TPL .= "\nrequire_once 'ast/$file';";
}

file_put_contents("$DIR/ast.php", $TPL);
