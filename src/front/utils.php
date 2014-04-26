<?php

namespace phs\front;

use phs\front\ast\Name;
use phs\front\ast\Ident;

require_once __DIR__ . '/../util/map.php';
require_once __DIR__ . '/../util/set.php';
require_once __DIR__ . '/../util/table.php';

function ident_to_str(Ident $id) {
  return $id->value;
}

function name_to_str(Name $name, $sep = '::') {
  return implode($sep, name_to_arr($name));
}

function name_to_arr(Name $name) {
  $items = [ ident_to_str($name->base) ];
  if ($name->parts)
    foreach ($name->parts as $part)
      $items[] = ident_to_str($part);
  return $items;
}

function array_copy_push(array $arr) { 
  // $arr is passed by value, so we have a copy
  for ($i = 1, $l = func_num_args(); $i < $l; ++$i)
    $arr[] = func_get_arg($i);
  return $arr;
}
