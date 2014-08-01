<?php

namespace phs\front;

require_once 'utils.php';
require_once 'visitor.php';
require_once 'scope.php';

use phs\Logger;
use phs\Session;

use phs\front\ast\Name;
use phs\front\ast\Ident;
use phs\front\ast\MemberExpr;
use phs\front\ast\TypeId;

/** expression reducer */
class Reducer extends Visitor
{
  // @var Session
  private $sess;
  
  // @var Scope
  private $scope;
  
  // @var Value   temporary value during ast-walking
  private $value;
  
  // @var Walker
  private $walker;
  
  /**
   * constructor
   *
   */
  public function __construct(Session $sess, Scope $scope)
  {
    // super
    parent::__construct();
    $this->sess = $sess;
    $this->scope = $scope;
    $this->walker = new Walker($this);
  }
  
  /**
   * reduces a node
   *
   * @param  Node $node
   * @return Value
   */
  public function reduce($node)
  {
    $this->value = null;
    $this->walker->walk_some($node);
    return $this->value;
  }
  
  // alias of reduce()
  public function reduce_expr($node)
  {
    return $this->reduce($node);
  }
  
  /* ------------------------------------ */
  
  /**
   * reduces a arithmetic operation
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_arithmetic_op($node)
  {
    static $kinds = [ VAL_KIND_INT, VAL_KIND_FLOAT ];
    
    $lhs = $this->reduce_expr($node->left);
    $rhs = $this->reduce_expr($node->right);
    
    if (!($this->convert_to_num($lhs) &&
          $this->convert_to_num($rhs)))
      return new Value(VAL_KIND_UNDEF);
    
    $kind = $kinds[(int) ($lhs->kind === VAL_KIND_FLOAT ||
                          $rhs->kind === VAL_KIND_FLOAT)];
    
    $data = 0;
    
    switch ($node->op) {
      case '+': $data = $lhs + $rhs; break;
      case '-': $data = $lhs - $rhs; break;
      case '*': $data = $lhs * $rhs; break;
      case T_POW: $data = pow($lhs, $rhs); break;
      
      case '/': case '%':
        if ($rhs == 0) { // "==" intended
          Logger::warn_at($node->loc, 'division by zero');
          
          // PHP: division by zero yields a boolean false
          $kind = VAL_KIND_BOOL;
          $data = false;
          break;
        }
        
        // PHP: division always results in a float
        $kind = VAL_KIND_FLOAT;
        
        switch ($node->op) {
          case '/': $data = $lhs / $rhs; break;
          case '%': $data = $lhs % $rhs; break;
          default: assert(0);
        }
        
        break;
      default: assert(0);
    }
    
    return new Value($kind, $data);
  }
  
  /**
   * reduces a bitwise operation
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_bitwise_op($node)
  {
    $lhs = $this->reduce_expr($node->left);
    $rhs = $this->reduce_expr($node->right);
    
    if (!($this->convert_to_int($lhs) &&
          $this->convert_to_int($rhs)))
      return new Value(VAL_KIND_UNDEF);
    
    $data = 0;
    
    switch ($node->op) {
      case '^': $data = $lhs ^ $rhs; break;
      case '&': $data = $lhs & $rhs; break;
      case '|': $data = $lhs | $rhs; break;
      case T_SL: $data = $lhs << $rhs; break;
      case T_SR: $data = $lhs >> $rhs; break;
      default: assert(0);
    }
    
    return new Value(VAL_KIND_INT, $data);
  }
  
  /**
   * reduces a logical operation
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_logical_op($node)
  {
    $lhs = $this->reduce_expr($node->left);
    $rhs = $this->reduce_expr($node->right);
    
    if ($lhs->kind === VAL_KIND_UNDEF ||
        $rhs->kind === VAL_KIND_UNDEF)
      return new Value(VAL_KIND_UNDEF);
    
    $data = false;
    
    switch ($node->op) {
      case '>': case '<': 
      case T_GTE:
      case T_LTE:
        if (!($this->convert_to_num($lhs) &&
              $this->convert_to_num($rhs)))
          return new Value(VAL_KIND_UNDEF);
        
        switch ($node->op) {
          case '>': $data = $lhs > $rhs; break;
          case '<': $data = $lhs < $rhs; break;
          case T_GTE: $data = $lhs >= $rhs; break;
          case T_LTR: $data = $lhs <= $rhs; break;
          default: assert(0);
        }
        
        break;
      
      case T_EQ: $data = $lhs === $rhs; break;
      case T_NEQ: $data = $lhs !== $rhs; break;
      default: assert(0);
    }
    
    return new Value(VAL_KIND_BOOL, $data);
  }
  
  /**
   * reduces a boolean operation  
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_boolean_op($node)
  {
    $lhs = $this->reduce_expr($node->left);
    $rhs = null;
    
    if ($lhs->kind === VAL_KIND_UNDEF)
      return new Value(VAL_KIND_UNDEF);
    
    $data = false;
    
    switch ($node->op) {
      case T_BOOL_AND:
        if (!$lhs->data) break;
        
        $rhs = $this->reduce_expr($node->right);
        
        if ($rhs->kind === VAL_KIND_UNDEF)
          return new Value(VAL_KIND_UNDEF);
        
        if (!$rhs->data) break;
        
        $data = true;
        break;
        
      case T_BOOL_OR:
        if ($lhs->data) {
          $data = true;
          break;
        }
        
        $rhs = $this->reduce_expr($node->right);
        
        if ($rhs->kind === VAL_KIND_UNDEF)
          return new Value(VAL_KIND_UNDEF);
        
        $data = !!$rhs->data;
        break;
        
      case T_BOOL_XOR:
        $rhs = $this->reduce_expr($node->right);
        
        if ($rhs->kind === VAL_KIND_UNDEF)
          return new Value(VAL_KIND_UNDEF);
        
        $lvl = (bool)$lhs->data;
        $rvl = (bool)$rhs->data;
        
        $data = ($lvl && !$rvl) || (!$lvl && $rvl);
        break;
        
      default: assert(0);
    }
    
    return new Value(VAL_KIND_BOOL, $data);          
  }
  
  /**
   * reduces a string-concat operation
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_concat_op($node)
  {
    $lhs = $this->reduce_expr($node->left);
    $rhs = $this->reduce_expr($node->right);
    
    if (!($this->convert_to_str($lhs) &&
          $this->convert_to_str($rhs)))
      return new Value(VAL_KIND_UNDEF);
    
    $data = $lhs->data . $rhs->data;
    return new Value(VAL_KIND_STR, $data);
  }
  
  /* ------------------------------------ */
  
  /**
   * converts the given value to a string
   *
   * @param  Value  $val
   * @return boolean
   */
  protected function convert_to_str(Value $val)
  {
    if ($val->kind === VAL_KIND_STRING)
      return true;
    
    $data = $val->data;
    
    switch ($val->kind) {
      case VAL_KIND_FLOAT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
        $data = (string) $data;
        break;
        
      case VAL_KIND_NULL: 
        $data = ''; 
        break;
      
      case VAL_KIND_LIST:
      case VAL_KIND_DICT:
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
        // TODO
        return false;
      default:
        assert(0);
    }
    
    $val->kind = VAL_KIND_STRING;
    $val->data = $data;
    
    return true;
  }
  
  /**
   * converts the given value to an int
   *
   * @param  Value  $val
   * @return boolean
   */
  protected function convert_to_int(Value $val)
  {
    if ($val->kind === VAL_KIND_INT)
      return true;
    
    $data = $val->data;
    $okay = true;
    
    switch ($val->kind) {
      case VAL_KIND_FLOAT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
        $data = (int) $data;
        break;
        
      case VAL_KIND_NULL: 
        $data = 0; 
        break;
      
      case VAL_KIND_LIST:
      case VAL_KIND_DICT:
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
        return false;
      default:
        assert(0);
    }
    
    $val->kind = VAL_KIND_INT;
    $val->data = $data;
    
    return true;
  }
  
  /**
   * converts the given value to a float
   *
   * @param  Value  $val
   * @return boolean
   */
  protected function convert_to_float(Value $val)
  {
    if ($val->kind === VAL_KIND_FLOAT)
      return true;
    
    $data = $val->data;
    
    switch ($val->kind) {
      case VAL_KIND_INT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
        $data = (float) $data;
        break;
        
      case VAL_KIND_NULL: 
        $data = 0.0; 
        break;
      
      case VAL_KIND_LIST:
      case VAL_KIND_DICT:
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
        return false;
      default:
        assert(0);
    }
    
    $val->kind = VAL_KIND_FLOAT;
    $val->data = $data;
    
    return true;
  }
  
  /**
   * converts the given value to a number
   *
   * @param  Value  $val
   * @return boolean
   */
  protected function convert_to_num(Value $val)
  {
    if ($val->kind === VAL_KIND_INT ||
        $val->kind === VAL_KIND_FLOAT)
      return true;
    
    return $this->convert_to_float($val);
  }
  
  /**
   * converts a value to a boolean
   *
   * @param  Value  $val
   * @return boolean
   */
  protected function convert_to_bool(Value $val)
  {
    if ($val->kind === VAL_KIND_BOOL)
      return true;
    
    $data = $val->data;
    
    switch ($val->kind) {
      case VAL_KIND_INT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
        $data = (bool) $data;
        break;
        
      case VAL_KIND_NULL: 
        $data = false; 
        break;
      
      case VAL_KIND_LIST:
      case VAL_KIND_DICT:
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
        return false;
      default:
        assert(0);
    }
    
    $val->kind = VAL_KIND_BOOL;
    $val->data = $data;
    
    return true;
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_name()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_name($node)
  {
    return $this->lookup_name($node);
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_ident($node)
  {
    return $this->lookup_path([ ident_to_str($node) ], false);
  }
  
  /**
   * Visitor#visit_fn_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_fn_expr($node) 
  {
    // TODO: we can not call a function at compile-time...
    // this may get changed sometime
    $this->value = new Value(VAL_KIND_UNDEF);  
  }
  
  /**
   * Visitor#visit_bin_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_bin_expr($node) 
  {
    switch ($node->op) {
      // arithmetic 
      case '+': case '-': case '*':
      case '/': case '%': case T_POW:
        $this->value = $this->reduce_arithmetic_op($node);
        break;
      // bitwise
      case '^': case '&': case '|': 
      case T_SL: case T_SR:
        $this->value = $this->reduce_bitwise_op($node);
        break;
      // logical
      case '>': case '<': case T_GTE:
      case T_LTE: case T_EQ: case T_NEQ:
        $this->value = $this->reduce_logical_op($node);
        break;
      // boolean
      case T_BOOL_AND:
      case T_BOOL_OR:
      case T_BOOL_XOR:
        $this->value = $this->reduce_boolean_op($node);
        break;
      case '~':
        $this->value = $this->reduce_concat_op($node);
        break;
      // in/not-in and range
      case T_IN:
      case T_NIN:
      case T_RANGE:
        // TODO: implement constant lists/dicts first
        $this->value = new Value(VAL_KIND_UNDEF);
        break;
    }  
  }
  
  /**
   * Visitor#visit_check_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_check_expr($node) 
  {
    $lhs = $this->reduce_expr($node->left);
    $sym = $this->lookup_name($node->right);
    
    if (!$sym || $lhs->kind !== VAL_KIND_NEW)
      $this->value = new Value(VAL_KIND_UNDEF);
    else
      $this->value = new Value(VAL_KIND_BOOL, $lhs->csym === $sym);
  }
  
  /**
   * Visitor#visit_cast_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_cast_expr($node) 
  {
    $lhs = $this->reduce_expr($node->expr);
    
    if (!$cty || $lhs->kind === VAL_KIND_UNDEF)
      goto err;
        
    if ($node->type instanceof TypeId) {
      switch ($lhs->kind) {
        case VAL_KIND_LIST:
        case VAL_KIND_DICT:
        case VAL_KIND_NEW:
          // not castable at compile-time
          goto err;
      }   
      
      $vdup = clone $lhs;
      $stat = false;
      
      switch ($node->type->type) {
        case T_TINT:
          $stat = $this->convert_to_int($vdup);
          break;
        case T_TBOOL:
          $stat = $this->convert_to_bool($vdup);
          break;
        case T_TFLOAT:
          $stat = $this->convert_to_float($vdup);
          break;
        case T_TREGEXP: /* TODO: remove this type? */
        case T_TSTRING:
          $stat = $this->convert_to_str($vdup);
          break;
        default: assert(0);
      }
      
      if (!$stat) goto err;
      
      $this->value = $vdup;
      goto out;
    } 
      
    err:
    $this->value = new Value(VAL_KIND_UNDEF);
    
    out:
    return;
  }
  
  /**
   * Visitor#visit_update_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_update_expr($node) 
  {
    // throw value away
    $this->reduce_expr($node->expr);
    $this->value = new Value(VAL_KIND_UNDEF);
  }
  
  /** 
   * Visitor#visit_assign_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_assign_expr($node) 
  {
    if (!($node->left instanceof Name) &&
        !($node->left instanceof Ident) &&
        !($node->left instanceof MemberExpr)) {
      Logger::error_at($node->left->loc, 'invalid assigment left-hand-side');
      $this->value = new Value(VAL_KIND_UNDEF);
    } else
      $this->reduce_assign_op($node);
  }
  
  /**
   * Visitor#visit_member_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_member_expr($node) 
  {
    $this->reduce_expr($node->obj);
    
    if ($node->computed)
      $this->reduce_expr($node->member);
    
    // TODO: implement constant dicts
    $this->value = new Value(VAL_KIND_UNDEF);  
  }
  
  /**
   * Visitor#visit_cond_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_cond_expr($node) 
  {
    $test = $this->reduce_expr($node->test);
    
    if ($test->kind === VAL_KIND_UNDEF)
      $this->value = new Value(VAL_KIND_UNDEF);
    else {
      // TODO: node should be replaced then!
      
      $this->convert_to_bool($test);
      
      if ($test->data === true) 
        $this->value = $this->reduce_expr($node->then);
      else
        $this->value = $this->reduce_expr($node->els);
    }
  }
  
  /**
   * Visitor#visit_call_expr()
   *
   * @param  Node $n
   * @return void
   */
  public function visit_call_expr($node) 
  {
    // calls are not possible during compile
    $this->value = new Value(VAL_KIND_UNDEF);  
  }
  
  /**
   * Visitor#visit_yield_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_yield_expr($node) 
  {
    // yield is runtime-only
    $this->value = new Value(VAL_KIND_UNDEF);  
  }
  
  /**
   * Visitor#visit_unary_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_unary_expr($node) 
  {
    $rhs = $this->reduce_expr($node->expr);
    
    if ($rhs->kind === VAL_KIND_UNDEF)
      goto err;
    
    $rdup = clone $rhs;
    $stat = false;
    
    switch ($node->op) {
      case '-':
      case '+':
        $stat = $this->convert_to_num($rdup);
        break;
      case '~':
        $stat = $this->convert_to_int($rdup);
        break;
      case '!':
        $stat = $this->convert_to_bool($rdup);
        break;
      default: assert(0);
    } 
    
    if (!$stat) goto err;
    
    $data = $rdup->data;
    
    switch ($node->op) {
      case '-': $data = -$data; break;
      case '+': $data = +$data; break;
      case '~': $data = ~$data; break;
      case '!': $data = !$data; break;
      default: assert(0);
    }
    
    $rdup->data = $data;
    $this->value = $rdup;
    
    goto out;
    
    err:
    $this->value = new Value(VAL_KIND_UNDEF);
    
    out:
    return;
  }
  
  public function visit_new_expr($n) {}
  public function visit_del_expr($n) {}
  public function visit_lnum_lit($n) {}
  public function visit_dnum_lit($n) {}
  public function visit_snum_lit($n) {}
  public function visit_regexp_lit($n) {}
  public function visit_arr_lit($n) {}
  public function visit_obj_lit($n) {}
  public function visit_this_expr($n) {}
  public function visit_super_expr($n) {}
  public function visit_null_lit($n) {}
  public function visit_true_lit($n) {}
  public function visit_false_lit($n) {}
  public function visit_engine_const($n) {}
  public function visit_str_lit($n) {}
  public function visit_type_id($n) {}
}
