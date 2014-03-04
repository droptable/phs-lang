<?php

namespace phs;

use phs\ast\Ident;
use phs\ast\Name;

/**
 * returns the string-value of an ident-node
 * 
 * @param  Ident  $id
 * @return string
 */
function ident_to_str(Ident $id) {
  return $id->value;
}

/**
 * returns an array of plain string for a name
 * 
 * @param Name $name
 * @return array
 */
function name_to_stra(Name $name) {
  $arr = [ ident_to_str($name->base) ];
  
  if ($name->parts)
    foreach ($name->parts as $part)
      $arr[] = ident_to_str($part);
  
  return $arr;
}

/**
 * returns a string-representation of a name
 * 
 * @param Name $name
 * @return string
 */
function name_to_str(Name $name) {
  return implode('::', name_to_stra($name));
}

/**
 * converts symbol-flags to a readable string
 * 
 * @param  int $flags
 * @return string
 */
function symflags_to_str($flags) {
  if ($flags === SYM_FLAG_NONE)
    return 'none';
  
  $res = [];
  
  static $check = [ 
    [ SYM_FLAG_CONST, 'const' ],
    [ SYM_FLAG_FINAL, 'final' ],
    [ SYM_FLAG_GLOBAL, 'global' ],
    [ SYM_FLAG_STATIC, 'static' ],
    [ SYM_FLAG_PUBLIC, 'public' ],
    [ SYM_FLAG_PRIVATE, 'private' ],
    [ SYM_FLAG_PROTECTED, 'protected' ],
    [ SYM_FLAG_SEALED, 'sealed' ],
    [ SYM_FLAG_INLINE, 'inline' ],
    [ SYM_FLAG_EXTERN, 'extern' ],
    [ SYM_FLAG_ABSTRACT, 'abstract' ],
    [ SYM_FLAG_INCOMPLETE, 'incomplete' ],
    [ SYM_FLAG_WEAK, 'weak' ]
  ];
  
  foreach ($check as $flg)
    if ($flags & $flg[0])
      $res[] = $flg[1];
  
  return implode(',', $res);
}

/**
 * converts modifiers to flags
 * 
 * @param  array $mods
 * @param  int   $base
 * @return int
 */
function mods_to_symflags($mods, $base = SYM_FLAG_NONE) {
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
  
  return $base;
}

/**
 * converts a module-path to string
 * 
 * @param  Name|array  $path
 * @param  boolean $root
 * @return string
 */
function path_to_str($path, $root = true) {
  if ($path instanceof Name) {
    $root = $path->root;
    $path = name_to_stra($path);
  }
  
  return ($root ? '::' : '') . implode('::', $path);
}

/**
 * same as path_to_str but using a base (root)
 * 
 * @param  Module $base
 * @param  Name|array $name
 * @return string
 */
function abspath_to_str(Module $base, $path) {
  return $base->path() . '::' . path_to_str($path);
}

/**
 * returns a symbol-kind as string
 * 
 * @param  int $kind
 * @return string
 */
function symkind_to_str($kind) {
  switch ($kind) {
    case SYM_KIND_MODULE:
      return 'module';
    case SYM_KIND_CLASS:
      return 'class';
    case SYM_KIND_TRAIT:
      return 'trait';
    case SYM_KIND_IFACE:
      return 'iface';
    case SYM_KIND_VAR:
      return 'variable';
    case SYM_KIND_FN:
      return 'function';
    default:
      return '(unknown kind=' . $kind . ')';
  }
}


/**
 * returns a reference-kind as string
 * 
 * @param  int $kind
 * @return string
 */
function refkind_to_str($kind) {
  assert($kind > SYM_REF_DIVIDER);
  return symkind_to_str($kind - SYM_REF_DIVIDER) . '-ref';
}

