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
        $this->value = new Value(VAL_KIND_BOOL, true);
        return true;
      case 'false_lit':
        $this->value = new Value(VAL_KIND_BOOL, false);
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
    var_dump($lhs);
    
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
        // TODO: implement constant objects
        return false;
      }
    } else {
      // array-access
      // TODO: implement constant arrays
      return false;
    }
  }
  
  /* ------------------------------------ */
    
  /**
   * lookup a name
   * 
   * @param  Name   $name
   * @return boolean
   */
  protected function lookup_name(Name $name)
  {    
    $bid = ident_to_str($name->base);
    $sym = $this->scope->get($bid, false, null, true);
    $mod = null;
    
    // its not a symbol in the current scope
    if ($sym === null) {
      // check if the $bid is a global module
      $mrt = $this->ctx->get_module();
      
      if ($mrt->has_child($bid)) {
        if (empty ($name->parts))
          // module can not be a value
          return false;  
        
        $mod = $mrt->get_child($bid);
        goto lcm;
      }
      
      return false;
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
          // a module can not be used as a value
          return false;
        
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
    if (empty ($name->parts)) {
      if ($sym->kind === SYM_KIND_VAR) {
        if ($sym->value !== null) {
          $this->value = $sym->value;
          return true;
        }
        
        return false;
      }
      
      $this->value = new Value(VAL_KIND_SYMBOL, $sym);
      return true;
    }
    
    /* ------------------------------------ */
    /* symbol lookup */
    
    // lookup other parts
    if ($sym->kind === SYM_KIND_VAR) {
      // the var could be a reference to a module
      // TODO: is this allowed?
      if ($sym->value === null)
        return false;
      
      switch ($sym->value->kind) {
        case REF_KIND_MODULE:
          $sym = $sym->value;
          break;
          
        default:
          // a subname lookup is not possible
          // this is an error actually, but fail silent here
          return false;
      }
    }
    
    if ($sym->kind !== REF_KIND_MODULE)
      return false;
    
    $mod = $sym->mod;
    
    /* ------------------------------------ */
    /* symbol lookup in module */
    
    lcm:
    return $this->lookup_child($mod, $name);
  }
  
  /**
   * lookup a symbol inside of a module
   * 
   * @param  Module $mod
   * @param  Name   $name the full-name
   * @pram   boolean $ig ignore base
   * @return boolean
   */
  protected function lookup_child(Module $mod, Name $name, $ib = true)
  {
    $arr = name_to_stra($name);
    $lst = array_pop($arr);
    
    // ignore base
    if ($ib) array_shift($arr);
    
    $res = $mod->fetch($arr);
    
    if ($res === null)
      return false;
    
    if ($res->has_child($lst))
      // module can not be a value
      return false;
    
    $sym = $res->get($lst);
    
    if ($sym === null)
      return false;
    
    if ($sym->kind === REF_KIND_VAR)
      $sym = $sym->sym;
    
    if ($sym->kind === SYM_KIND_VAR) {
      if ($sym->value !== null) {
        $this->value = $sym->value;
        return true;
      }
      
      // symbol does not have a value
      return false;
    }
    
    if ($sym->kind > SYM_REF_DIVIDER) {
      if ($sym->kind === REF_KIND_MODULE)
        // module can not be a value
        return false;
      
      $sym = $sym->sym;
    }
    
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
