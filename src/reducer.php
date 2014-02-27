<?php

namespace phs;

use phs\ast\Node;
use phs\ast\Name;

require_once 'value.php';
require_once 'symbol.php';

/** reduces expressions at compile-time (if possible) */
class Reducer 
{
  // compiler context
  private $ctx;
  
  // scope
  private $scope;
  
  // module
  private $module;
  
  // the current value 
  private $value;
  
  // value stack
  private $vstack;
  
  /**
   * constructor 
   * 
   * @param Context $ctx
   */
  public function __construct(Context $ctx)
  {
    $this->ctx = $ctx;
  }
  
  /**
   * reduces a expression
   * 
   * @param  Node $node
   * @param  Scope  $scope
   * @return mixed
   */
  public function reduce(Node $node, Scope $scope, Module $module = null)
  {
    $this->scope = $scope;
    $this->module = $module;
    $this->value = null;
    $this->vstack = [];
    
    if (!$this->reduce_expr($node))
      return new Value(VAL_KIND_UNKNOWN);
    
    return $this->value;
  }
  
  /* ------------------------------------ */
  
  protected function reduce_expr($expr)
  {
    $kind = $expr->kind();
    
    switch ($kind) {
      // atom
      case 'name':
        return $this->lookup_name($expr);
      case 'str_lit':
        $this->value = new Value(VAL_KIND_STR, $expr->value);
        return true;
      case 'lnum_lit':
        $this->value = new Value(VAL_KIND_LNUM, $expr->value);
        return true;
      case 'dnum_lit':
        $this->value = new Value(VAL_KIND_DNUM, $expr->value);
        return true;
      case 'snum_lit':
        return false; // number with suffix
      case 'null_lit':
        $this->value = new Value(VAL_KIND_NULL);
        return true;
      case 'true_lit':
        $this->value = new Value(VAL_KIND_TRUE);
        return true;
      case 'false_lit':
        $this->value = new Value(VAL_KIND_FALSE);
        return true;
      case 'regexp_lit':
        $this->value = new Value(VAL_KIND_REGEXP, $expr->value);
        return true;
      case 'engine_const':
        // TODO: the analyzer should know this
        return false;
      
      // expressions
      case 'unary_expr':
        return $this->reduce_unary_expr($expr);
      case 'bin_expr':
        return $this->reduce_bin_expr($expr);
      case 'check_expr':
        return $this->reduce_check_expr($expr);
      case 'update_expr':
        return $this->reduce_update_expr($expr);
      case 'cast_expr':
        return $this->reduce_cast_expr($expr);
        return false;  
      case 'member_expr':
        return $this->reduce_member_expr($expr);
      case 'cond_expr':
        return $this->reduce_cond_expr($expr);
      case 'call_expr':
        // TODO: is this reducible? well, maybe it is, but its kinda hard to do so...
        return false;
      case 'new_expr':
        // TODO: is this reducible? this gonna be fun!
        return false;
      case 'del_expr':
        // TODO: what should the delete-operator return? NULL? TRUE? a number?
        return false;
      case 'yield_expr':
        // TODO: is this reducible? the value depends on what was send back to the iterator
        return false;
        
      // other stuff
      default:
        return false;
    }
  }
  
  protected function reduce_bin_expr($expr)
  {    
    if (!$this->reduce_expr($expr->left))
      goto err;
    
    $lhs = $this->value;
    
    if (!$this->reduce_expr($expr->right))
      goto err;
    
    $rhs = $this->value;
    
    switch ($expr->op->type) {
      case T_BIT_NOT:
        $this->value = new Value(VAL_KIND_STR, $lhs->value . $rhs->value);
        return true;
    }
    
    err:
    return false;
  }
  
  protected function reduce_member_expr($expr)
  {
    if (!$this->reduce_expr($expr->obj))
      goto err;
    
    $obj = $this->value;
    $key = null;
    
    if ($expr->computed) {
      if (!$this->reduce_expr($expr->member))
        goto err;
      
      // accessing it direclty
      $key = $this->value->value;  
    } else
      $key = $expr->member->value;
      
    if ($expr->prop) {
      // property-access
      $sym = null;
      
      if ($obj->kind === VAL_KIND_SYMBOL && 
          ($obj->value->kind === SYM_KIND_CLASS || 
           $obj->value->kind === SYM_KIND_TRAIT ||
           $obj->value->kind === SYM_KIND_IFACE)) {
        // fetch member
        $sym = $obj->value->mst->get((string) $key, false);
        if (!$sym) return false; // symbol not found
        
        // if symbol is constant or it is static and untouched and it has a value
        if ((($sym->flags & SYM_FLAG_CONST) || (($sym->flags & SYM_FLAG_STATIC) && 
               $sym->writes === 0)) && $sym->value !== null) {
          $this->value = $sym->value;
          return true;
        } else {
          // accessing non-static/non-const or empty property
          return false;
        }
        
      } else {
        // TODO: implement constant objects
        return false;
      }
    } else {
      // array-access
      // TODO: implement constant arrays
      return false;
    }
    
    err:
    return false;
  }
  
  /* ------------------------------------ */
  
  protected function lookup_name(Name $name)
  {    
    $bid = ident_to_str($name->base);
    $sym = $this->scope->get($bid, false, null, true);
    
    // its not a symbol in the current scope
    if ($sym === null)
      return $this->lookup_module($name);
    
    switch ($sym->kind) {
      // symbols
      case SYM_KIND_CLASS:
      case SYM_KIND_TRAIT:
      case SYM_KIND_IFACE:
        $this->value = new Value(VAL_KIND_SYMBOL, $sym);
        return true;
      
      case SYM_KIND_VAR:
        // best case: no members
        if (empty ($name->parts)) {
          if ($sym->value !== null) {
            $this->value = $sym->value;
            return true;
          }
          
          return false;
        }
        
        // this is an error actually,
        // except the value of this symbol is a module-ref
        // must be implemented somehow
        return false;
      
      case SYM_KIND_FN:
        // well, this is tricky
        return false;
      
      // references
      case REF_KIND_MODULE:
        return $this->lookup_module($sym->path);
        
      case REF_KIND_CLASS:
      case REF_KIND_TRAIT:
      case REF_KIND_IFACE:
      case REF_KIND_VAR:
        // track the reference
        return $this->lookup_name($sym->path);
        
      case REF_KIND_FN:
        return false;
        
      default:
        print 'what? ' . $sym->kind();
        exit;
    }
  }
  
  protected function lookup_module(Name $name)
  {
    $mod = $this->ctx->get_module();
    $arr = name_to_stra($name);
    $nam = array_pop($arr);
    $sym = $mod->fetch($arr);
    
    if ($sym === null) return false;
    
    if ($sym->has_child($nam)) {
      // a module can not be used as a value
      return false;
    }
    
    $sym = $sym->get($nam, false);
    if ($sym === null) return false;
    
    $this->value = new Value(VAL_KIND_SYMBOL, $sym);
    return true;
  }
  
  /* ------------------------------------ */
  
  private function push()
  {
    array_push($this->vstack, $this->value);
  }
  
  private function pop()
  {
    $this->value = array_pop($this->vstack);
  }
}
