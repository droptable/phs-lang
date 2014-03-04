<?php

namespace phs;

use phs\ast\Name;

/** name lookup helpers */

/**
 * lookup a name
 * 
 * @param  Name   $name
 * @return Symbol
 */
function lookup_name(Name $name, Scope $scope, Context $ctx, $track = false) {    
  $bid = ident_to_str($name->base);
  $sym = $scope->get($bid, false, null, true);
  $mod = null;
  
  // its not a symbol in the current scope
  if ($sym === null) {
    // check if the $bid is a global module
    $mrt = $ctx->get_module();
    
    if ($mrt->has_child($bid)) {
      if (empty ($name->parts))
        // module can not be referenced
        return null;  
      
      $mod = $mrt->get_child($bid);
      goto lcm;
    }
    
    return null;
  }
  
  switch ($sym->kind) {
    // symbols
    case SYM_KIND_CLASS:
    case SYM_KIND_TRAIT:
    case SYM_KIND_IFACE:
    case SYM_KIND_VAR:
    case SYM_KIND_FN:
      break;
    
    // references
    case REF_KIND_MODULE:
      if (empty ($name->parts))
        // a module can not be referenced
        return null;
      
      $mod = $sym->mod;
      goto lcm;
      
    case REF_KIND_CLASS:
    case REF_KIND_TRAIT:
    case REF_KIND_IFACE:
    case REF_KIND_VAR:
    case REF_KIND_FN:
      $sym = $sym->sym;
      break;
      
    default:
      print 'what? ' . $sym->kind;
      exit;
  }
  
  // best case: no more parts
  if (empty ($name->parts))
    return $sym;
  
  /* ------------------------------------ */
  /* symbol lookup */
  
  // lookup other parts
  if ($sym->kind === SYM_KIND_VAR) {
    // the var could be a reference to a module
    // TODO: is this allowed?
    if ($sym->value === null)
      return null;
    
    switch ($sym->value->kind) {
      case REF_KIND_MODULE:
        $sym = $sym->value;
        break;
        
      default:
        // a subname lookup is not possible
        // this is an error actually, but fail silent here
        return null;
    }
  }
  
  if ($sym->kind !== REF_KIND_MODULE)
    return null;
  
  $mod = $sym->mod;
  
  /* ------------------------------------ */
  /* symbol lookup in module */
  
  lcm:
  return lookup_child($mod, $name);
}

/**
 * lookup a symbol inside of a module
 * 
 * @param  Module $mod
 * @param  Name   $name the full-name
 * @pram   boolean $ig ignore base
 * @return Symbol
 */
function lookup_child(Module $mod, Name $name, $ib = true) {
  $arr = name_to_stra($name);
  $lst = array_pop($arr);
  
  // ignore base
  if ($ib) array_shift($arr);
  
  $res = $mod->fetch($arr);
  
  if ($res === null)
    return null;
  
  if ($res->has_child($lst))
    // module can not be referenced
    return null;
  
  $sym = $res->get($lst);
  
  if ($sym === null)
    return null;
    
  if ($sym->kind > SYM_REF_DIVIDER) {
    if ($sym->kind === REF_KIND_MODULE)
      // module can not be a referenced
      return null;
    
    $sym = $sym->sym;
  }
  
  return $sym;
}
