<?php

namespace phs;

use phs;

use phs\ast\Name;
use phs\ast\Ident;

const DS = \DIRECTORY_SEPARATOR;

require_once 'util/map.php';
require_once 'util/set.php';
require_once 'util/result.php';

require_once 'symbols.php';

/**
 * joins a path and replaces "\" to "\\"
 *
 * @param  ...
 * @return string
 */
function join_path() {
  return strtr(implode(DS, func_get_args()), [ '\\' => '\\\\' ]);
}

/**
 * var_dump()'s stuff into a file
 *
 * @param  mixed $var
 * @param  string $file
 */
function var_dump_into($var, $file) {
  ob_start();
  var_dump($var);
  file_put_contents($file, ob_get_clean());
}

/**
 * updates a ident
 *
 * @param  Ident  $id 
 * @param  string $set
 */
function ident_set(Ident $id, $set) {
  $id->data = $set;
}

/**
 * converts a ast-ident to a string
 *
 * @param  Ident $id
 * @return string
 */
function ident_to_str(Ident $id) {
  return $id->data;
}

/**
 * converts a ast-name to a string
 *
 * @param  Name   $name
 * @param  string $sep
 * @return string
 */
function name_to_str(Name $name, $sep = '::') {
  $path = arr_to_path(name_to_arr($name), $name->root, $sep);
  if ($name->type) {
    $prfx = type_to_str($name->type);
    $path = "$prfx::$path";
  }
  
  return $path;
}

/**
 * converts a ast-name to an array
 *
 * @param  Name   $name
 * @return array
 */
function name_to_arr(Name $name) {
  $items = [ ident_to_str($name->base) ];
  if ($name->parts)
    foreach ($name->parts as $part)
      $items[] = ident_to_str($part);
  return $items;
}

/**
 * converts an array to a path
 *
 * @param  array   $arr 
 * @param  boolean $root
 * @param  string  $sep 
 * @return string
 */
function arr_to_path(array $arr, $root = false, $sep = '::') {
  $res = '';
  
  foreach ($arr as $val) {
    // array means: [ separator, name ]
    if (is_array($val)) {
      $res .= $val[0];
      $val = $val[1];
    } else
      $res .= $sep;
    
    $res .= $val;
  }
  
  $res = ltrim($res, '.:');
  
  if ($root) $res = "$sep$res";
  
  return $res;
}

// alias of arr_to_path()
function arr_to_name(array $a, $r = false, $s = '::') {
  return arr_to_path($a, $r, $s);
}

// same as arr_to_path() but <root> = true
function path_to_str(array $a, $s = '::') {
  // wrong prototype
  if (func_num_args() === 3) {
    trigger_error('path_to_str() expected 2 arguments but found (at least) 3',
      E_USER_DEPRECATED);
    
    $s = func_get_arg(3);  
  }
    
  return arr_to_path($a, true, $s);
}

/**
 * converts a path to an unique-id
 *
 * @param  array  $a
 * @return string
 */
function path_to_uid(array $a) {
  $r = '';
  
  foreach ($a as $p)
    $r .= strlen($p) . $p;
  
  return $r;
}

/**
 * returns the path as namespace
 *
 * @param  array  $path
 * @return string
 */
function path_to_ns(array $path) {
  return implode('\\', $path);
}

/**
 * returns the path as absolute namespace
 *
 * @param  array  $path
 * @return string
 */
function path_to_abs_ns(array $path) {
  return '\\' . path_to_ns($path);
}

/**
 * returns a crc32 checksum as string
 *
 * @param  string $val
 * @return string
 */
function crc32_str($val) {
  return sprintf('%u', crc32($val));
}

/**
 * returns the name of a type
 *
 * @param  int $type
 * @return string
 */
function type_to_str($type) {
  static $map = [
    T_TINT => 'int',
    T_TFLOAT => 'float',
    T_TTUP => 'tup',
    T_TBOOL => 'bool',
    T_TSTR => 'str',
    T_TDEC => 'dec',
    T_TANY => 'any',
    T_SELF => 'self',
  ];
    
  return $map[$type];
}

/**
 * converts ast-modifiers to an array of printable strings
 *
 * @param  array $mods
 * @return array
 */
function mods_to_arr($mods) {
  return sym_flags_to_stra(mods_to_sym_flags($mods));
}

/**
 * converts ast-modifiers to symbol-flags
 *
 * @param  array $mods
 * @param  int $base
 * @return int
 */
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
          break;
        case T_UNSAFE:
          $base |= SYM_FLAG_UNSAFE; 
          break;
        case T_NATIVE:
          $base |= SYM_FLAG_NATIVE;
          break;
        case T_HIDDEN:
          $base |= SYM_FLAG_HIDDEN;
          break;
        default:
          asssert(0); 
      }
    }
  }
  
  return $base;
}

/**
 * converts symbol-flags to an array
 *
 * @param  int $flags
 * @return array
 */
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
    SYM_FLAG_PARAM,
    SYM_FLAG_UNSAFE,
    SYM_FLAG_NATIVE,
    SYM_FLAG_HIDDEN
  ];
  
  foreach ($check as $flag)
    if ($flags & $flag)
      $arr[] = $flag;
    
  return $arr;
}

/**
 * converts symbol-flags to an array of printable strings
 *
 * @param  int $flags
 * @return array
 */
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
    SYM_FLAG_PARAM => 'parameter',
    SYM_FLAG_UNSAFE => 'unsafe',
    SYM_FLAG_NATIVE => 'native',
    SYM_FLAG_HIDDEN => 'hidden'
  ];
  
  foreach (sym_flags_to_arr($flags) as $flag)
    $res[] = $check[$flag];
  
  return $res;
}

/**
 * converts symbol-flags to a string
 *
 * @param  int $flags
 * @return string
 */
function sym_flags_to_str($flags) {
  return implode(', ', sym_flags_to_stra($flags));
}

/**
 * computes the difference of two symbol-flag vars
 *
 * @param  int $a
 * @param  int $b
 * @return object { add -> additional flags, del -> deleted flags }
 */
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
    SYM_FLAG_PARAM,
    SYM_FLAG_UNSAFE,
    SYM_FLAG_NATIVE,
    SYM_FLAG_HIDDEN
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

/**
 * converts a symbol-flag to a string
 *
 * @param  int $kind
 * @return string 
 */
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
