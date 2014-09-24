<?php

namespace phs\front;

use phs\Logger;

use phs\front\ast\Name;
use phs\front\ast\Ident;

require_once __DIR__ . '/../util/map.php';
require_once __DIR__ . '/../util/set.php';
require_once __DIR__ . '/../util/result.php';

require_once 'symbols.php';

function var_dump_into($var, $file) {
  ob_start();
  var_dump($var);
  file_put_contents($file, ob_get_clean());
}

function ident_to_str($id) {
  if (!($id instanceof Ident))
    throw new \RuntimeException;
  return $id->data;
}

function name_to_str(Name $name, $sep = '::') {
  $path = arr_to_path(name_to_arr($name), $name->root, $sep);
  if ($name->self) $path = "self::$path";
  return $path;
}

function name_to_arr(Name $name) {
  $items = [ ident_to_str($name->base) ];
  if ($name->parts)
    foreach ($name->parts as $part)
      $items[] = ident_to_str($part);
  return $items;
}

function arr_to_path(array $arr, $root = false, $sep = '::') {
  $res = implode($sep, $arr);
  if ($root) $res = "$sep$res";
  return $res;
}

// alias of arr_to_path()
function arr_to_name(array $a, $r = false, $s = '::') {
  return arr_to_path($a, $r, $s);
}

// alias of arr_to_path()
function path_to_str(array $a, $r = false, $s = '::') {
  return arr_to_path($a, $r, $s);
}

function mods_to_arr($mods) {
  return sym_flags_to_stra(mods_to_sym_flags($mods));
}

function mods_to_sym_flags($mods, $base = SYM_FLAG_NONE) {
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

function sym_flags_to_arr($flags) {
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
    SYM_FLAG_INCOMPLETE,
    SYM_FLAG_PARAM
  ];
  
  foreach ($check as $flag)
    if ($flags & $flag)
      $arr[] = $flag;
    
  return $arr;
}

function sym_flags_to_stra($flags) {
  if ($flags === SYM_FLAG_NONE)
    return [ 'none' ];
  
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
    SYM_FLAG_INCOMPLETE => 'incomplete',
    SYM_FLAG_PARAM => 'parameter'
  ];
  
  foreach (sym_flags_to_arr($flags) as $flag)
    $res[] = $check[$flag];
  
  return $res;
}

function sym_flags_to_str($flags) {
  return implode(', ', sym_flags_to_stra($flags));
}

function sym_flags_diff($a, $b) {
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
    SYM_FLAG_INCOMPLETE,
    SYM_FLAG_PARAM
  ];
  
  $d = new \stdclass;
  $d->add = SYM_FLAG_NONE;
  $d->del = SYM_FLAG_NONE;
  
  foreach ($check as $f)
    if (($a & $f) && !($b & $f))
      $d->del |= $f;
    elseif (($b & $f) && !($a & $f))
      $d->add |= $f;
    
  $d->add &= ~SYM_FLAG_INCOMPLETE;
  $d->del &= ~SYM_FLAG_INCOMPLETE;
  
  return $d;
}

function sym_kind_to_str($kind) {
  switch ($kind) {
    case SYM_KIND_FN:
      return 'function';
    case SYM_KIND_VAR:
      return 'variable';
    case SYM_KIND_CLASS:
      return 'class';
    case SYM_KIND_TRAIT:
      return 'trait';
    case SYM_KIND_IFACE:
      return 'iface';
    case SYM_KIND_ALIAS:
      return 'alias';
    default:
      assert(0);
  }
}
