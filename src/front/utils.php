<?php

namespace phs\front;

use phs\front\ast\Name;
use phs\front\ast\Ident;

require_once __DIR__ . '/../util/map.php';
require_once __DIR__ . '/../util/set.php';

require_once 'symbols.php';

function var_dump_into($var, $file) {
  ob_start();
  var_dump($var);
  file_put_contents($file, ob_get_clean());
}

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

function mods_to_flags($mods, $base = SYM_FLAG_NONE) {
  if ($mods) {
    foreach ($mods as $mod) {
      switch ($mod->type) {
        case T_CONST:
          $base |= SYM_FLAG_CONST;
          break;
        case T_FINAL:
          $base |= SYM_FLAG_FINAL;
          break;
        case T_GLOBAL:
          $base |= SYM_FLAG_GLOBAL;
          break;    
        case T_STATIC:
          $base |= SYM_FLAG_STATIC;
          break;   
        case T_PUBLIC:
          $base |= SYM_FLAG_PUBLIC;
          break;
        case T_PRIVATE:
          $base |= SYM_FLAG_PRIVATE;
          break; 
        case T_PROTECTED:
          $base |= SYM_FLAG_PROTECTED;
          break;
        case T_SEALED:
          $base |= SYM_FLAG_SEALED;
          break;  
        case T_INLINE: 
          $base |= SYM_FLAG_INLINE;
          break;   
        case T_EXTERN:
          $base |= SYM_FLAG_EXTERN;    
      }
    }
  }
  
  return $base;
}

function flags_to_arr($flags) {
  $arr = [];
  
  static $check = [ 
    SYM_FLAG_CONST,
    SYM_FLAG_FINAL,
    SYM_FLAG_GLOBAL,
    SYM_FLAG_STATIC,
    SYM_FLAG_PUBLIC,
    SYM_FLAG_PRIVATE,
    SYM_FLAG_PROTECTED,
    SYM_FLAG_SEALED,
    SYM_FLAG_INLINE,
    SYM_FLAG_EXTERN,
    SYM_FLAG_ABSTRACT,
    SYM_FLAG_INCOMPLETE
  ];
  
  foreach ($check as $flag)
    if ($flags & $flag)
      $arr[] = $flag;
    
  return $arr;
}

function flags_to_stra($flags) {
  if ($flags === SYM_FLAG_NONE)
    return 'none';
  
  $res = [];
  
  static $check = [ 
    SYM_FLAG_CONST => 'const',
    SYM_FLAG_FINAL => 'final',
    SYM_FLAG_GLOBAL => 'global',
    SYM_FLAG_STATIC => 'static',
    SYM_FLAG_PUBLIC => 'public',
    SYM_FLAG_PRIVATE => 'private',
    SYM_FLAG_PROTECTED => 'protected',
    SYM_FLAG_SEALED => 'sealed',
    SYM_FLAG_INLINE => 'inline',
    SYM_FLAG_EXTERN => 'extern',
    SYM_FLAG_ABSTRACT => 'abstract',
    SYM_FLAG_INCOMPLETE => 'incomplete'
  ];
  
  foreach (flags_to_arr($flags) as $flag)
    $res[] = $check[$flag];
  
  return $res;
}

function flags_to_str($flags) {
  return implode(', ', flags_to_stra($flags));
}

function dump_scope(Scope $scope) {
  if ($scope instanceof Module)
    foreach ($scope->subm as $mod) {
      print "-> {$mod->id}\n";
      dump_scope($mod);
    }
    
  elseif ($scope->prev)
    dump_scope($scope->prev);
  
  foreach ($scope as $ns)
    foreach ($ns as $sym)
      print "{$sym->id}\n";
}