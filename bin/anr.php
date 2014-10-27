<?php

$AST = realpath(__DIR__ . '/../src/ast');

foreach (glob("$AST/*.php") as $file) {
  echo "processing ", $file, "\n";
  
  $data = file_get_contents($file);
  $data = preg_replace(
    '/^(\s*)namespace phs\\\\front\\\\ast\s*;$/m', 
    '$1namespace phs\\ast;',
    $data  
  );
  
  file_put_contents($file, $data);
}
