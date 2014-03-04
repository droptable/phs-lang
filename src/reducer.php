<?php

namespace phs;

use phs\ast\Node;
use phs\ast\Name;

require_once 'value.php';
require_once 'symbol.php';
require_once 'lookup.php';

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
    
    if (!$this->reduce_expr($node))
      return new Value(VAL_KIND_UNKNOWN);
    
    return $this->value;
  }
  
  /* ------------------------------------ */
  
  protected function reduce_expr($expr)
  {
    $kind = $expr->kind();
    #print "reducing $kind\n";
    
    switch ($kind) {
      // atom
      case 'name':
        return $this->lookup($expr);
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
        // TODO: implement
        return false;
      case 'null_lit':
        $this->value = new Value(VAL_KIND_NULL);
        return true;
      case 'true_lit':
        $this->value = new Value(VAL_KIND_BOOL, true);
        return true;
      case 'false_lit':
        $this->value = new Value(VAL_KIND_BOOL, false);
        return true;
      case 'regexp_lit':
        $this->value = new Value(VAL_KIND_REGEXP, $expr->value);
        return true;
        
      // array/object literals only get pre-compiled if they 100% constant.
      // method-calls on the reduced values like this are not supported though
      case 'arr_lit':
        return $this->reduce_arr_lit($expr);
      case 'obj_lit':
        return $this->reduce_obj_lit($expr);
      
      case 'engine_const':
        // TODO: the analyzer should know this
        return false;
      
      // expressions
      case 'unary_expr':
        return $this->reduce_unary_expr($expr);
      case 'bin_expr':
        return $this->reduce_bin_expr($expr);
      /*
      case 'check_expr':
        return $this->reduce_check_expr($expr);
      case 'update_expr':
        return $this->reduce_update_expr($expr);
      case 'cast_expr':
        return $this->reduce_cast_expr($expr);
        return false;
      */
      case 'member_expr':
        return $this->reduce_member_expr($expr);
      /*
      case 'cond_expr':
        return $this->reduce_cond_expr($expr);
      */
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
      
      case 'fn_expr':
        return false;
        
      // other stuff
      default:
        return false;
    }
  }
  
  protected function reduce_arr_lit($expr)
  {
    $res = [];
    
    foreach ($expr->items as $item) {
      if (!$this->reduce_expr($item)) {
        return false;
      }
      
      $res[] = $this->value;
    }
    
    $this->value = new Value(VAL_KIND_ARR, $res);
    return true;
  }
  
  protected function reduce_obj_lit($expr)
  {
    // we're using an assoc-array here
    $res = [];
    
    foreach ($expr->pairs as $pair) {
      $kind = $pair->key->kind();
      
      if ($kind === 'ident' || $kind === 'str_lit')
        $key = $pair->key->value;
      else {
        if (!$this->reduce_expr($pair->key))
          return false;
        
        $key = (string)$this->value->value;
      }
      
      if (!$this->reduce_expr($pair->value))
        return false;
      
      $res[$key] = $this->value;
    }
    
    $this->value = new Value(VAL_KIND_OBJ, $res);
    return true;
  }
  
  protected function reduce_unary_expr($expr)
  {
    if (!$this->reduce_expr($expr->expr))
      return false;
        
    $rhs = $this->value;
    $rval = $rhs->value;
    $kind = $rhs->kind === VAL_KIND_DNUM ? VAL_KIND_DNUM : VAL_KIND_LNUM;
    
    switch ($expr->op->type) {
      case T_EXCL:
        $this->value = new Value(VAL_KIND_BOOL, !$rval);
        return true;
      case T_PLUS:
        $this->value = new Value($kind, +$rval);
        return true;
      case T_MINUS:
        $this->value = new Value($kind, -$rval);
        return true;
      case T_BIT_NOT:
        // PHP does not cast to int here ...
        // instead, it does a bitwise-not on the binary data
        // WTF
        $this->value = new Value(VAL_KIND_LNUM, ~(int)$rval);
        return true;
    }
    
    return false;
  }
  
  protected function reduce_bin_expr($expr)
  {    
    if (!$this->reduce_expr($expr->left))
      return false;
    
    $lhs = $this->value;
    
    if (!$this->reduce_expr($expr->right))
      return false;
    
    $rhs = $this->value;
    
    $lval = $lhs->value;
    $rval = $rhs->value;
    
    if ($expr->op->type === T_BIT_NOT) {
      // concat
      $this->value = new Value(VAL_KIND_STR, $lval . $rval);
      return true;
    }
    
    if ($lhs->kind === VAL_KIND_DNUM ||
        $rhs->kind === VAL_KIND_DNUM)
      $kind = VAL_KIND_DNUM;
    else 
      $kind = VAL_KIND_LNUM;
    
    switch ($expr->op->type) {
      case T_PLUS:
        $this->value = new Value($kind, $lval + $rval);
        return true;
      case T_MINUS:
        $this->value = new Value($kind, $lval - $rval);
        return true;
      case T_MUL:
        $this->value = new Value($kind, $lval * $rval);
        return true;
      case T_DIV:
      case T_MOD:
        if ((float) $rval === 0.) {
          $this->error_at($expr->op->loc, ERR_WARN, 'division by zero');
          
          if (!is_numeric($rval))
            $this->error_at($expr->right->loc, ERR_WARN, '^ caused by implicit convertion of "%s"', $rval);
          
          // this is a PHP-thing, NaN or 0 would be better
          $this->value = new Value(VAL_KIND_BOOL, false);
          return true;
        }
        
        if ($expr->op->type === T_MOD) {
          $kind = VAL_KIND_LNUM;
          $comp = $lval % $rval;
        } else
          $comp = $lval / $rval;
        
        $this->value = new Value($kind, $comp);
        return true;
      case T_POW:
        $this->value = new Value($kind, pow($lval, $rval));
        return true;
      case T_BIT_AND:
        $this->value = new Value(VAL_KIND_LNUM, $lval & $rval);
        return true;
      case T_BIT_OR:
        $this->value = new Value(VAL_KIND_LNUM, $lval | $rval);
        return true;
      case T_BIT_XOR:
        $this->value = new Value(VAL_KIND_LNUM, $lval ^ $rval);
        return true;
      case T_GT:
        $this->value = new Value(VAL_KIND_BOOL, $lval > $rval);
        return true;
      case T_LT:
        $this->value = new Value(VAL_KIND_BOOL, $lval < $rval);
        return true;
      case T_GTE:
        $this->value = new Value(VAL_KIND_BOOL, $lval >= $rval);
        return true;
      case T_LTE:
        $this->value = new Value(VAL_KIND_BOOL, $lval <= $rval);
        return true;
      case T_BOOL_AND:
        $this->value = new Value(VAL_KIND_BOOL, $lval && $rval);
        return true;
      case T_BOOL_OR:
        $this->value = new Value(VAL_KIND_BOOL, $lval || $rval);
        return true;
      case T_BOOL_XOR:
        $this->value = new Value(VAL_KIND_BOOL, ($lval && !$rval) || (!$lval && $rval));
        return true;
      case T_RANGE:
        // TODO: implement constant arrays
        return false;
      case T_SL:
        $this->value = new Value(VAL_KIND_LNUM, $lval << $rval);
        return true;
      case T_SR:
        $this->value = new Value(VAL_KIND_LNUM, $lval >> $rval);
        return true;
      case T_EQ:
        $this->value = new Value(VAL_KIND_BOOL, $lval === $rval);
        return true;
      case T_NEQ:
        $this->value = new Value(VAL_KIND_BOOL, $lval !== $rval);
        return true;
      case T_IN:
        // TODO: implement constant arrays
        return false; 
    }
    
    return false;
  }
  
  protected function reduce_member_expr($expr)
  {
    if (!$this->reduce_expr($expr->obj))
      return false;
    
    $obj = $this->value;
    $key = null;
    
    if ($expr->computed) {
      if (!$this->reduce_expr($expr->member))
        return false;
      
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
        if ($obj->kind !== VAL_KIND_OBJ)
          return false;
        
        $key = (string) $key;
        if (!isset ($obj->value[$key]))
          return false;
        
        $this->value = $obj->value[$key];
        return true;
      }
    } else {
      // array-access
      if ($obj->kind !== VAL_KIND_ARR)
        return false;
      
      if (!is_int($key) && !ctype_digit($key))
        return false; // simply refuse
      
      $key = (int) $key;
      
      if (!isset ($obj->value[$key]))
        return false;
        
      $this->value = $obj->value[$key];
      return true;      
    }
  }
  
  /* ------------------------------------ */
    
  /**
   * lookup a name
   * 
   * @param  Name   $name
   * @return boolean
   */
  protected function lookup(Name $name)
  { 
    $sym = lookup_name($name, $this->scope, $this->ctx);
    
    // lookup failed
    if ($sym === null) return false;
    
    if ($sym->kind === SYM_KIND_VAR) {
      if ($sym->flags & SYM_FLAG_CONST && $sym->value !== null) {
        $this->value = $sym->value;
        return true;
      }
    }
    
    $this->value = new Value(VAL_KIND_SYMBOL, $sym);
    return true;
  }
  
  /* ------------------------------------ */
  
  /**
   * error handler
   * 
   */
  public function error_at()
  {
    $args = func_get_args();    
    $loc = array_shift($args);
    $lvl = array_shift($args);
    $msg = array_shift($args);
    
    $this->ctx->verror_at($loc, COM_RDC, $lvl, $msg, $args);
  }
}
