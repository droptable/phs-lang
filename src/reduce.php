<?php

namespace phs;

// TODO: split trait and class here
//  trait -> just operations (+ - / * ... NOT reduce_obj_lit() !)
//  class -> everything else

require_once 'utils.php';
require_once 'scope.php';
require_once 'lookup.php';

use phs\ast\Unit;
use phs\ast\Expr;
use phs\ast\Name;
use phs\ast\Ident;
use phs\ast\StrLit;
use phs\ast\ObjKey;
use phs\ast\MemberExpr;
use phs\ast\OffsetExpr;
use phs\ast\Param;
use phs\ast\RestParam;

trait LateBindings
{
  // @var array 
  private $fnstk;
  
  // @var ClassSymbol
  private $cclass;
  
  // @var TraitSymbol
  private $ctrait;
  
  /**
   * computes a absolute path to a symbol
   *
   * @param  Symbol $sym
   * @return string
   */
  private function compute_sym_path($sym)
  {
    $path = $sym->path();
    $pstr = path_to_str($sym->path());
    
    if (!empty ($path))
      $pstr .= '::';
    
    return $pstr . $sym->id;
  }
  
  /**
   * reduces a __class__ constant (early binding only)
   *
   * @param  Node $node
   */
  private function reduce_class_const($node)
  {
    if ($this->cclass === null) {
      if ($this->ctrait === null) {
        Logger::warn_at($node->loc, '`__class__` is not defined in this scope');
        $node->value = Value::$EMPTY;
      }
        
      return; // late binding  
    }
    
    $path = $this->compute_sym_path($this->cclass);
    $node->value = new Value(VAL_KIND_STR, $path);
  }
  
  /**
   * reduces a __method__ constant (early binding only)
   *
   * @param  Node $node
   */
  private function reduce_method_const($node)
  {
    if (empty ($this->fnstk) || ($this->cclass === null && $this->ctrait === null)) {
      Logger::warn_at($node->loc, '`__method__` is not defined in this scope');
      $node->value = Value::$EMPTY;
      return;
    }
    
    if ($this->cclass === null)
      // delay to late binding
      return;
    
    $path = $this->compute_sym_path($this->cclass);
    $path .= '.' . $this->compute_fn_path();
    
    $node->value = new Value(VAL_KIND_STR, $path);
  }
}

/** expression reduce */
trait Reduce
{  
  use LateBindings;
  
  /**
   * checks if a node has a value
   *
   * @param  Node  $node
   * @return boolean
   */
  private function has_value($node)
  {
    return $node instanceof Expr && 
           $node->value && 
           $node->value->is_some();
  }
  
  /**
   * returns the value of a node
   *
   * @param  Node $node
   * @return Value
   */
  private function get_value($node)
  {
    if ($node instanceof Expr && $node->value)
      return $node->value;
    
    return Value::$UNDEF;
  }
  
  /**
   * reduces a paren_expr
   *
   * @param Node $node
   */
  public function reduce_paren_expr($node) 
  {
    if ($this->has_value($node))
      return;
    
    $node->value = $this->get_value($node->expr);
  }
  
  /**
   * reduces a tuple_expr
   *
   * @param Node $node
   */
  public function reduce_tuple_expr($node) 
  {
    if ($this->has_value($node))
      return;
    
    if (!$node->seq)
      $node->value = new Value(VAL_KIND_TUPLE, []);
    else {
      $okay = true;
      $node->value = Value::$UNDEF;
      
      foreach ($node->seq as $item)
        if ($this->get_value($item)->is_unkn()) {
          $okay = false;
          break;
        }
        
      if ($okay) {
        $tupl = [];
        
        foreach ($node->seq as $item)
          $tupl[] = $this->get_value($item);
        
        $node->value = new Value(VAL_KIND_TUPLE, $tupl);
      }
    }
  }
  
  /**
   * reduces a bin_expr
   *
   * @param Node $node
   */
  public function reduce_bin_expr($node)
  {    
    if ($this->has_value($node))
      return;
    
    $lval = $this->get_value($node->left);
    $rval = $this->get_value($node->right);
    
    if ($lval->is_unkn() || $rval->is_unkn())
      $node->value = Value::$UNDEF;
    else
      switch ($node->op->type) {
        // arithmetic 
        case T_PLUS: case T_MINUS: 
        case T_MUL: case T_DIV: 
        case T_MOD: case T_POW:
          $node->value = $this->reduce_arithmetic_op($node, $lval, $rval);
          break;
        // bitwise
        case T_BIT_XOR: case T_BIT_AND: 
        case T_BIT_OR: 
        case T_SL: case T_SR:
          $node->value = $this->reduce_bitwise_op($node, $lval, $rval);
          break;
        // logical
        case T_GT: case T_LT:  case T_GTE:
        case T_LTE: case T_EQ: case T_NEQ:
          $node->value = $this->reduce_logical_op($node, $lval, $rval);
          break;
        // boolean
        case T_BOOL_AND: case T_BOOL_OR: 
        case T_BOOL_XOR:
          $node->value = $this->reduce_boolean_op($node, $lval, $rval);
          break;
        case T_CONCAT:
          $node->value = $this->reduce_concat_op($node, $lval, $rval);
          break;
        // in/not-in and range
        case T_IN: case T_NIN:
          $node->value = $this->reduce_in_op($node, $lval, $rval);
          break;
        // range
        case T_RANGE:
          $node->value = $this->reduce_range_op($node, $lval, $rval);
          break;
        default:
          assert(0);
      }  
  }
  
  /**
   * reduces a range operation
   *
   * @param  Node $node
   * @param  Value $lval
   * @param  Value $rval
   * @return Value
   */
  protected function reduce_range_op($node, $lval, $rval)
  {
    static $kinds = [ VAL_KIND_INT, VAL_KIND_FLOAT ];
    
    $lres = $lval->to_num();
    $rres = $rval->to_num();
    
    $out = Value::$UNDEF;
    
    if ($lres->is_some() && $rres->is_some()) {
      $kind = $kinds[(int) ($lval->kind === VAL_KIND_FLOAT ||
                            $rval->kind === VAL_KIND_FLOAT)];
      
      $lval = &$lres->unwrap();
      $rval = &$rres->unwrap();
      
      $rang = range($lval->data, $rval->data);
      $data = [];
      
      foreach ($rang as $num)
        $data[] = new Value($kind, $num);     
      
      $out = new Value(VAL_KIND_LIST, $data);
    }
    
    return $out;
  }
  
  /**
   * reduces a arithmetic operation
   *
   * @param  Node  $node
   * @param  Value $lval
   * @param  Value $rval
   * @return Value
   */
  protected function reduce_arithmetic_op($node, $lval, $rval)
  {
    static $kinds = [ VAL_KIND_INT, VAL_KIND_FLOAT ];
    
    $lres = $lval->to_num();
    $rres = $rval->to_num();
    
    $out = Value::$UNDEF;
    
    if ($lres->is_some() && $rres->is_some()) {
      $data = 0;
      $lval = &$lres->unwrap();
      $rval = &$rres->unwrap();
      $kind = $kinds[(int) ($lval->kind === VAL_KIND_FLOAT ||
                            $rval->kind === VAL_KIND_FLOAT)];
      
      $lhs = &$lval->data;
      $rhs = &$rval->data;
      
      switch ($node->op->type) {
        case T_PLUS: case T_APLUS:  
          $data = $lhs + $rhs; 
          break;
        case T_MINUS: case T_AMINUS: 
          $data = $lhs - $rhs; 
          break;
        case T_MUL: case T_AMUL:   
          $data = $lhs * $rhs; 
          break;
        case T_POW: case T_APOW:   
          $data = pow($lhs, $rhs); 
          break;
        
        case T_DIV: case T_ADIV:
        case T_MOD: case T_AMOD:
          if ($rhs == 0) { // "==" intended
            Logger::warn_at($node->loc, 'division by zero');
            
            // PHP: division by zero yields a boolean false
            $kind = VAL_KIND_BOOL;
            $data = false;
            break;
          }
          
          // PHP: division always results in a float
          $kind = VAL_KIND_FLOAT;
          
          switch ($node->op->type) {
            case T_DIV: case T_ADIV:
              $data = $lhs / $rhs; 
              break;
            case T_MOD: case T_AMOD:
              $data = $lhs % $rhs; 
              break;
            default: assert(0);
          }
          
          break;
        default: assert(0);
      }
    
      $out = new Value($kind, $data);
    }
    
    return $out;
  }
  
  /**
   * reduces a bitwise operation
   *
   * @param  Node  $node
   * @param  Value $lval
   * @param  Value $rval
   * @return Value
   */
  protected function reduce_bitwise_op($node, $lval, $rval)
  {
    $lres = $lval->to_int();
    $rres = $rval->to_int();
    
    $out = Value::$UNDEF;
    
    if ($lres->is_some() && $rres->is_some()) {
      $data = 0;
      
      $lhs = &$lres->unwrap()->data;
      $rhs = &$rres->unwrap()->data;
      
      switch ($node->op->type) {
        case T_BIT_XOR: case T_ABIT_OR:
          $data = $lhs ^ $rhs; 
          break;
        case T_BIT_AND: case T_ABIT_AND:
          $data = $lhs & $rhs; 
          break;
        case T_BIT_OR: case T_ABIT_OR:
          $data = $lhs | $rhs; 
          break;
        case T_SL: case T_ASHIFT_L:
          $data = $lhs << $rhs; 
          break;
        case T_SR: T_ASHIFT_R:
          $data = $lhs >> $rhs; 
          break;
        default: assert(0);
      }
      
      $out = new Value(VAL_KIND_INT, $data);
    }
    
    return $out;
  }
  
  /**
   * reduces a logical operation
   *
   * @param  Node  $node
   * @param  Value $lval
   * @param  Value $rval
   * @return Value
   */
  protected function reduce_logical_op($node, $lval, $rval)
  {
    $data = false;
    
    $lhs = $lval->data;
    $rhs = $rval->data;
    
    switch ($node->op->type) {
      case T_GT: case T_LT: 
      case T_GTE: case T_LTE:
        $lres = $lval->to_num();
        $rres = $rval->to_num();
        
        $out = Value::$UNDEF;
        
        if ($lres->is_some() && $rres->is_some()) {
          $lhs = &$lres->unwrap()->data;
          $rhs = &$rres->unwrap()->data;
          
          switch ($node->op->type) {
            case T_GT:  $data = $lhs > $rhs; break;
            case T_LT:  $data = $lhs < $rhs; break;
            case T_GTE: $data = $lhs >= $rhs; break;
            case T_LTR: $data = $lhs <= $rhs; break;
            default: assert(0);
          }
          
          $out = new Value(VAL_KIND_BOOL, $data);
        }
        
        return $out;
      
      case T_EQ:  $data = $lhs === $rhs; break;
      case T_NEQ: $data = $lhs !== $rhs; break;
      default: assert(0);
    }
    
    return new Value(VAL_KIND_BOOL, $data);
  }
  
  /**
   * reduces a boolean operation  
   *
   * @param  Node  $node
   * @param  Value $lval
   * @param  Value $rval
   * @return Value
   */
  protected function reduce_boolean_op($node, $lval, $rval)
  {
    $lres = $lval->to_bool();
    $rres = $rval->to_bool();
    
    $out = Value::$UNDEF;
    
    if ($lres->is_some() && $rres->is_some()) {
      $lhs = &$lres->unwrap()->data;
      $rhs = &$rres->unwrap()->data;
      $data = false;
        
      switch ($node->op->type) {
        case T_BOOL_AND: case T_ABOOL_AND: 
          $data = $lhs && $rhs; 
          break;
        case T_BOOL_OR: case T_ABOOL_OR: 
          $data = $lhs || $rhs; 
          break;
        case T_BOOL_XOR: case T_ABOOL_XOR:        
          $data = ($lhs && !$rhs) || (!$lhs && $rhs);
          break;
          
        default: assert(0);
      }
      
      $out = new Value(VAL_KIND_BOOL, $data);      
    }  
    
    return $out;
  }
  
  /**
   * reduces a string-concat operation
   *
   * @param  Node  $node
   * @param  Value $lval
   * @param  Value $rval
   * @return Value
   */
  protected function reduce_concat_op($node, $lval, $rval)
  {
    $lres = $lval->to_str();
    $rres = $rval->to_str();
    
    $out = Value::$UNDEF;
    
    if ($lres->is_some() && $rres->is_some()) {
      $lhs = &$lres->unwrap()->data;
      $rhs = &$rres->unwrap()->data;
      $out = new Value(VAL_KIND_STR, $lhs . $rhs);
    }
    
    return $out;
  } 
  
  /**
   * reduces a in-operation
   *
   * @param  Node  $node
   * @param  Value $lval
   * @param  Value $rval
   * @return Value
   */
  protected function reduce_in_op($node, $lval, $rval)
  {
    // TODO
    return Value::$UNDEF;
  }
  
  /**
   * reduces a check_expr
   *
   * @param Node $node
   */
  public function reduce_check_expr($n) {}
  
  /**
   * reduces a cast_expr
   *
   * @param Node $node
   */
  public function reduce_cast_expr($n) {}
  
  /**
   * reduces a update_expr
   *
   * @param Node $node
   */
  public function reduce_update_expr($n) {}
  
  /**
   * reduces a assign_expr
   *
   * @param Node $node
   */
  public function reduce_assign_expr($node) 
  {
    if ($this->has_value($node))
      return;
    
    $node->value = $this->get_value($node->right);
  }
  
  /**
   * reduces a member_expr
   *
   * @param Node $node
   */
  public function reduce_member_expr($node) 
  {
    if ($this->has_value($node))
      return;
    
    $obj = $this->get_value($node->object);
    $key = null;
    
    if ($node->computed) 
      $mem = $this->get_value($node->member);
    else
      $key = $node->member->data;
    
    if ($obj->is_unkn() || ($key === null && $mem->is_unkn()))
      $node->value = Value::$UNDEF;
    else {
      // check object
      if ($obj->kind !== VAL_KIND_DICT) {
        $node->value = Value::$UNDEF;
        return;
        
      // check offset
      } elseif ($key === null) {
        if ($mem->kind !== VAL_KIND_STR) {
          // try to convert the given offset to a int
          $con = $mem->to_str();
          
          if (!$con->is_some()) {        
            Logger::error_at($node->offset->loc, 'illegal member-subscript type');
            Logger::info_at($node->offset->loc, 'expected a string-ish value');
            Logger::info_at($node->offset->loc, 'value is %s', $mem->inspect());
            $node->value = Value::$UNDEF;
            return;
          }
          
          $mem = &$con->unwrap();
        }
        
        $key = $mem->data;
        
      // reduce
      } // else { }
      
      $node->value = $obj->data[$key];  
    }
  }
  
  /**
   * reduces a offset_expr
   *
   * @param Node $node
   */
  public function reduce_offset_expr($node) 
  {
    if ($this->has_value($node))
      return;
    
    $obj = $this->get_value($node->object);
    $off = $this->get_value($node->offset);
    
    if ($obj->is_unkn() || $off->is_unkn())
      $node->value = Value::$UNDEF;
    else {
      // check object
      if ($obj->kind !== VAL_KIND_LIST &&
          $obj->kind !== VAL_KIND_TUPLE &&
          $obj->kind !== VAL_KIND_STR) {
        Logger::error_at($node->object->loc, 'illegal offset left-hand-side');
        $node->value = Value::$UNDEF;
        return;
        
      // check offset
      } elseif ($off->kind !== VAL_KIND_INT) {
        // try to convert the given offset to a int
        $con = $off->to_int();
        
        if (!$con->is_some()) {        
          Logger::error_at($node->offset->loc, 'illegal offset type');
          Logger::info_at($node->offset->loc, 'expected a integer-ish value');
          Logger::info_at($node->offset->loc, 'value is %s', $off->inspect());
          $node->value = Value::$UNDEF;
          return;
        }
        
        $off = &$con->unwrap();
        
      // reduce
      } // else { }
      
      $node->value = $obj->data[$off->data];
    }
  }
  
  /**
   * reduces a cond_expr
   *
   * @param Node $node
   */
  public function reduce_cond_expr($node) 
  {
    if ($this->has_value($node))
      return;
    
    $tst = $this->get_value($node->test);
    
    if ($tst->is_unkn())
      goto err;
    
    if ($node->then)
      $thn = $this->get_value($node->then);
    else
      // no "then" expression -> use "test"
      $thn = $tst;
    
    $els = $this->get_value($node->els);
    
    if ($thn->is_unkn() || $els->is_unkn())
      goto err;
    
    // convert test to bool
    $tst = $tst->to_bool();
    
    if ($tst->is_some()) {
      $node->value = $tst->data ? $thn : $els;
      goto out;
    }
    
    err:
    $node->value = Value::$UNDEF;
    
    out:
    return;
  }
  
  /**
   * reduces a call_expr
   *
   * @param Node $node
   */
  public function reduce_call_expr($n) {}
  
  /**
   * reduces a yield_expr
   *
   * @param Node $node
   */
  public function reduce_yield_expr($node) 
  {
    // "yield" can not be reduced
    $node->value = Value::$UNDEF;
  }
  
  /**
   * reduces a unary_expr
   *
   * @param Node $node
   */
  public function reduce_unary_expr($node) 
  {
    if ($this->has_value($node))
      return;
    
    $val = $this->get_value($node->expr);
    
    if ($val->is_unkn())
      $node->value = Value::$UNDEF;
    else
      switch ($node->op->type) {
        // arithmetic 
        case T_PLUS:
        case T_MINUS:
          $node->value = $this->reduce_uarithmetic_op($node, $val);
          break;
        // bitwise
        case T_BIT_NOT:
          $node->value = $this->reduce_ubitwise_op($node, $val);
          break;
        // logical
        case T_EXCL:
          $node->value = $this->reduce_ulogical_op($node, $val);
          break;
        // reference
        case T_TREF:
          // not reducible
          $node->value = Value::$UNDEF;
          break;
        default:
          assert(0);
      }  
  }
  
  /**
   * reduces a unary arithmetic op
   *
   * @param  Node $node
   * @param  Value $val
   * @return Value
   */
  protected function reduce_uarithmetic_op($node, $val)
  {
    $res = $val->to_num();
    $out = Value::$UNDEF;
    
    if ($res->is_some()) {
      $val = &$res->unwrap();
      $rhs = &$val->data;
      
      switch ($node->op->type) {
        case T_PLUS:
          $rhs = +$rhs; // noop?
          break;
        case T_MINUS:
          $rhs = -$rhs;
          break;
        default:
          assert(0);
      }
      
      $out = $val;
    }
    
    return $out;
  }
  
  /**
   * reduces a unary bitwise op
   *
   * @param  Node $node
   * @param  Value $val
   * @return Value
   */
  protected function reduce_ubitwise_op($node, $val)
  {
    $res = $val->to_int();
    $out = Value::$UNDEF;
    
    if ($res->is_some()) {
      $val = &$res->unwrap();
      $rhs = &$val->data;
      
      $rhs = ~$rhs;
      $out = $val;
    }
    
    return $out;
  }
  
  /**
   * reduces a unary logical op
   *
   * @param  Node $node
   * @param  Value $val
   * @return Value
   */
  protected function reduce_ulogical_op($node, $val)
  {
    $res = $val->to_bool();
    $out = Value::$UNDEF;
    
    if ($res->is_some()) {
      $val = &$res->unwrap();
      $rhs = &$val->data;
      
      $rhs = !$rhs;
      $out = $val;
    }
    
    return $out;
  }
  
  /**
   * reduces a new_expr
   *
   * @param Node $node
   */
  public function reduce_new_expr($node) 
  {
    // not reducible
    $node->value = Value::$UNDEF;
  }
  
  /**
   * reduces a del_expr
   *
   * @param Node $node
   */
  public function reduce_del_expr($node) 
  {
    // unset() in PHP does not return anything (not a expression)
    // (unset)$foo returns NULL ...
    // we return <nothing> here
    $node->value = Value::$NONE;
  }
  
  /**
   * reduces a lnum_lit
   *
   * @param Node $node
   */
  public function reduce_lnum_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    $node->value = new Value(VAL_KIND_INT, $node->data);
  }
  
  /**
   * reduces a dnum_lit
   *
   * @param Node $node
   */
  public function reduce_dnum_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    $node->value = new Value(VAL_KIND_FLOAT, $node->data);
  }
  
  /**
   * reduces a snum_lit
   *
   * @param Node $node
   */
  public function reduce_snum_lit($n) {}
  
  /**
   * reduces a regexp_lit
   *
   * @param Node $node
   */
  public function reduce_regexp_lit($n) {}
  
  /**
   * reduces a arr_gen
   *
   * @param Node $node
   */
  public function reduce_arr_gen($node) 
  {
    $node->value = Value::$UNDEF;
  }
  
  /**
   * reduces a arr_lit
   *
   * @param Node $node
   */
  public function reduce_arr_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    if (!$node->items)
      $node->value = new Value(VAL_KIND_LIST, []);
    else {
      $okay = true;
      $node->value = Value::$UNDEF;
      
      foreach ($node->items as $item)
        if ($this->get_value($item)->is_unkn()) {
          $okay = false;
          break;
        }
        
      if ($okay) {
        $list = [];
        
        foreach ($node->items as $item)
          $list[] = $this->get_value($item);
        
        $node->value = new Value(VAL_KIND_LIST, $list);
      }
    }  
  }
  
  /**
   * reduces a obj_lit
   *
   * @param Node $node
   */
  public function reduce_obj_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    if (!$node->pairs)
      $node->value = new Value(VAL_KIND_DICT, []);
    else {
      $okay = true;
      $node->value = Value::$UNDEF;
      
      foreach ($node->pairs as $pair)        
        if (($pair->key instanceof ObjKey &&
             $this->get_value($pair->key->expr)->is_unkn()) ||
            ($pair->key instanceof StrLit &&
             count($pair->key->parts) > 0) ||
            $this->get_value($pair->arg)->is_unkn()) {
          $okay = false;
          break;
        }
       
      if ($okay) {
        $dict = [];
        
        foreach ($node->pairs as $pair) {
          $key = null;
          
          // dynamic key
          if ($pair->key instanceof ObjKey) {
            $val = $this->get_value($pair->key->expr);
            
            if ($val->kind !== VAL_KIND_STR) {
              $res = $val->to_str();
                            
              if ($res->is_some())
                $val = &$res->unwrap();
              else {
                $loc = $pair->key->expr->loc;
                Logger::error_at($loc, 'illegal dictionary-key');
                Logger::info_at($loc, 'expected a string-ish value');
                Logger::info_at($loc, 'value is %s', $val->inspect());
                return;
              }
            }
            
            $key = $val->data;     
          } 
          
          // ident
          elseif ($pair->key instanceof Ident)
            $key = ident_to_str($pair->key);
          
          // string
          else {
            assert($pair->key instanceof StrLit);
            assert(count($pair->key->parts) === 0);
            $key = $pair->key->data; 
          }
              
          $dict[$key] = $this->get_value($pair->arg);
        }
        
        $node->value = new Value(VAL_KIND_DICT, $dict);
      }
    }  
  }
  
  /**
   * reduces a name
   *
   * @param Node $node
   * @param int  $acc
   */
  public function reduce_name($node) 
  {
    // not a constant expression
    $node->value = Value::$UNDEF;
  }
  
  /**
   * reduces a ident
   *
   * @param Node $node
   * @param int  $acc
   */
  public function reduce_ident($node) 
  {
    // not a constant expression
    $node->value = Value::$UNDEF;
  }
  
  /**
   * reduces a this_expr
   *
   * @param Node $node
   */
  public function reduce_this_expr($node) 
  {
    // not a constant expression
    $node->value = Value::$UNDEF;
  }
  
  /**
   * reduces a super_expr
   *
   * @param Node $node
   */
  public function reduce_super_expr($node) 
  {
    // not a constant expression
    $node->value = Value::$UNDEF;
  }
  
  /**
   * reduces a null_lit
   *
   * @param Node $node
   */
  public function reduce_null_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    $node->value = new Value(VAL_KIND_NULL);
  }
  
  /**
   * reduces a true_lit
   *
   * @param Node $node
   */
  public function reduce_true_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    $node->value = new Value(VAL_KIND_BOOL, true);
  }
  
  /**
   * reduces a false_lit
   *
   * @param Node $node
   */
  public function reduce_false_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    $node->value = new Value(VAL_KIND_BOOL, false);
  }
    
  /**
   * reduces a str_lit
   *
   * @param Node $node
   */
  public function reduce_str_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    if (empty ($node->parts)) {
      $out = new Value(VAL_KIND_STR, $node->data);
      goto out;
    }
    
    $hcf = $node->flag === 'c';
    $lst = [ $node ];
    $lhs = 0;
    $tok = null;
    
    $out = Value::$UNDEF;
    $okay = true;
    $part = null;
        
    foreach ($node->parts as $idx => &$part) {
      if ($idx & 1) {
        if ($lhs === -1) {
          $lhs = array_push($lst, $part) - 1;
          continue;
        }
        
        $lst[$lhs]->data .= $part->data;
        $part = null;
        continue;
      }
            
      $val = $this->get_value($part);
      
      if ($val->is_unkn())
        goto nxt;
      
      $vres = $val->to_str();
        
      if ($vres->is_some()) {
        $lst[$lhs]->data .= $vres->unwrap()->data;
        continue;
      }
      
      nxt:
      if ($hcf) goto err;
      
      array_push($lst, $part);
      $lhs = -1;
    }
    
    // first entry is the node itself
    $node->parts = array_slice($lst, 1);
    
    if (empty ($node->parts)) {
      $node->parts = null;
      $out = new Value(VAL_KIND_STR, $node->data);
    }
    
    goto out;
    
    err:
    Logger::error_at($part ? $part->loc : $node->loc, '\\');
    Logger::error('constant string-interpolation must be \\');
    Logger::error('convertible to a string value');
    
    out:
    $node->value = $out;
  }
  
  /**
   * reduces a kstr_lit
   *
   * @param Node $node
   */
  public function reduce_kstr_lit($node) 
  {
    if ($this->has_value($node))
      return;
    
    $node->value = new Value(VAL_KIND_STR, $node->data);
  }
  
  /**
   * reduces a type_id
   *
   * @param Node $node
   */
  public function reduce_type_id($node) 
  {
    // not 100% sure
    $node->value = Value::$UNDEF;
  } 
  
  /**
   * reduces an engine-constant to a value
   *
   * @param  Node $node
   */
  public function reduce_engine_const($node)
  {
    if ($this->has_value($node))
      return;
    
    switch ($node->type) {
      case T_CFN:
        $this->reduce_fn_const($node);
        break;
      case T_CCLASS:
        $this->reduce_class_const($node);
        break;
      case T_CTRAIT:
        $this->reduce_trait_const($node);
        break;
      case T_CMETHOD:
        $this->reduce_method_const($node);
        break;
      case T_CMODULE:
        $this->reduce_module_const($node);
        break;
      default:
        assert(0);
    }
  }
  
  /**
   * reduces a __fn__ constant
   *
   * @param  Node $node
   */
  public function reduce_fn_const($node)
  {
    if (empty ($this->fnstk)) {
      Logger::warn_at($node->loc, '`__fn__` is not defined in this scope');
      $node->value = Value::$EMPTY;
      return;  
    }
        
    // assign value
    $path = $this->compute_fn_path();
    $pstr = '';
    
    // prepend module-path if we're not in a method
    if (!$this->cclass && !$this->ctrait && $this->cmodule) {
      $pstr .= path_to_str($this->cmodule->path());
      $pstr .= '::';
    }
    
    $pstr .= $path;
    
    $node->value = new Value(VAL_KIND_STR, $pstr);
  }
  
  /**
   * computes the current function-path (without class-name)
   *
   * @return string
   */
  private function compute_fn_path()
  {
    $path = [];
    
    foreach ($this->fnstk as $fn)
      if ($fn === null)
        $path[] = '{closure}';
      else
        $path[] = $fn->id;
            
    return implode('/', $path);
  }
        
  /**
   * reduces a __trait__ constant (early binding only)
   *
   * @param  Node $node
   */
  public function reduce_trait_const($node)
  {
    if ($this->ctrait === null) {
      Logger::warn_at($node->loc, '`__trait__` is not defined in this scope');
      $node->value = Value::$EMPTY;
      return;
    }
    
    $path = $this->compute_sym_path($this->ctrait);
    $node->value = new Value(VAL_KIND_STR, $path);
  }
  
  /**
   * reduces a __module__ constant
   *
   * @param  Node $node
   */
  public function reduce_module_const($node)
  {
    if ($this->cmodule === null) {
      Logger::warn_at($node->loc, '`__module__` is not defined in this scope');
      $node->value = Value::$EMPTY;
      return;
    }
    
    $node->value = new Value(VAL_KIND_STR, path_to_str($this->cmodule->path()));
  }
}

/** unit reducer */
class ReduceTask extends Visitor implements Task
{  
  // mixin expression-reduce methods
  use Reduce;
  
  // @var Session
  private $sess;
  
  // @var array<FnSymbol>
  // for __fn__ and __method__
  private $fnstk;
  
  // @var ClassSymbol 
  // for __class__ and __method__
  private $cclass;
  
  // @var TraitSymbol
  // for __trait__
  // and __class__ and __method__
  private $ctrait;
  
  // @var ModuleScope
  // for __module__
  private $cmodule;
  
  /**
   * constructor
   *
   * @param Session $sess
   */
  public function __construct(Session $sess)
  {
    parent::__construct();
    $this->sess = $sess;
  }
  
  /**
   * reduces a unit
   *
   * @param  Unit   $unit
   * @return void
   */
  public function run(Unit $unit)
  {
    $this->fnstk = [];
    $this->visit($unit);
  }
  
  /**
   * Visitor#visit_module()
   *
   * @param Node $node
   */
  public function visit_module($node) 
  { 
    $prev = $this->cmodule;
    $this->cmodule = $node->scope;
    
    $this->visit($node->body);
    
    $this->cmodule = $prev;
  }
  
  /**
   * Visitor#visit_block()
   *
   * @param Node $node
   */
  public function visit_block($node) 
  { 
    $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param Node $node
   */
  public function visit_enum_decl($node) 
  {
    foreach ($node->vars as $var)
      if ($var->init) $this->visit($var->init);
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param Node $node
   */
  public function visit_class_decl($node) 
  {
    assert($node->symbol !== null);
    
    $this->cclass = $node->symbol;
    
    if ($node->members)
      foreach ($node->members as $member)
        $this->visit($member);
    
    $this->cclass = null;
  }
  
  /**
   * Visitor#visit_nested_mods()
   *
   * @param Node $node
   */
  public function visit_nested_mods($n) 
  {
    // should be gone
    assert(0);
  }
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param Node $node
   */
  public function visit_ctor_decl($node) 
  {  
    $this->visit_fn_params($node->params);
    $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param Node $node
   */
  public function visit_dtor_decl($node) 
  {  
    $this->visit_fn_params($node->params);
    $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_getter_decl()
   *
   * @param Node $node
   */
  public function visit_getter_decl($node) 
  {  
    $this->visit_fn_params($node->params);
    $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_setter_decl()
   *
   * @param Node $node
   */
  public function visit_setter_decl($node) 
  {   
    $this->visit_fn_params($node->params);
    $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_trait_decl()
   *
   * @param Node $node
   */
  public function visit_trait_decl($node) 
  {
    assert($node->symbol !== null);
    
    $this->ctrait = $node->symbol;
    
    if ($node->members)
      foreach ($node->members as $member)
        $this->visit($member);
      
    $this->ctrait = null;
  }
  
  /**
   * Visitor#visit_iface_decl()
   *
   * @param Node $node
   */
  public function visit_iface_decl($n) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_fn_decl()
   *
   * @param Node $node
   */
  public function visit_fn_decl($node) 
  {
    if ($node->symbol === null && $this->ctrait)
      return;
    
    assert($node->symbol);
    
    array_push($this->fnstk, $node->symbol);
    
    $this->visit_fn_params($node->params);
    
    if ($node->body)
      $this->visit($node->body);
    
    array_pop($this->fnstk);
  }
  
  /**
   * Visitor#visit_var_decl()
   *
   * @param Node $node
   */
  public function visit_var_decl($node) 
  {
    foreach ($node->vars as $var)
      if ($var->init) {
        $this->visit($var->init);
        $var->symbol->value = $var->init->value;
      }
  }
  
  /**
   * Visitor#visit_var_list()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_var_list($node) 
  {
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_use_decl()
   *
   * @param Node $node
   */
  public function visit_use_decl($n) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_require_decl()
   *
   * @param Node $node
   */
  public function visit_require_decl($node) 
  {
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_label_decl()
   *
   * @param Node $node
   */
  public function visit_label_decl($node) 
  {
    $this->visit($node->stmt);  
  }
  
  /**
   * Visitor#visit_do_stmt()
   *
   * @param Node $node
   */
  public function visit_do_stmt($node) 
  {
    $this->visit($node->stmt);
    $this->visit($node->test);  
  }
  
  /**
   * Visitor#visit_if_stmt()
   *
   * @param Node $node
   */
  public function visit_if_stmt($node) 
  {
    $this->visit($node->test);
    $this->visit($node->stmt);
    
    if ($node->elsifs) {
      foreach ($node->elsifs as $elsif) {
        $this->visit($elsif->test);
        $this->visit($elsif->stmt);
      }
    }
    
    if ($node->els)
      $this->visit($node->els->stmt);
  }
  
  /**
   * Visitor#visit_for_stmt()
   *
   * @param Node $node
   */
  public function visit_for_stmt($node) 
  {
    $this->visit($node->init);
    $this->visit($node->test);
    $this->visit($node->each);
    $this->visit($node->stmt); 
  }
  
  /**
   * Visitor#visit_for_in_stmt()
   *
   * @param Node $node
   */
  public function visit_for_in_stmt($node) 
  {
    $this->visit($node->rhs);
    $this->visit($node->stmt);
  }
  
  /**
   * Visitor#visit_try_stmt()
   *
   * @param Node $node
   */
  public function visit_try_stmt($node) 
  {
    $this->visit($node->body);
    
    if ($node->catches)
      foreach ($node->catches as $catch)
        $this->visit($catch->body);  
    
    if ($node->finalizer)
      $this->visit($node->finalizer->body);  
  }
  
  /**
   * Visitor#visit_php_stmt()
   *
   * @param Node $node
   */
  public function visit_php_stmt($n) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_goto_stmt()
   *
   * @param Node $node
   */
  public function visit_goto_stmt($n) 
  {
    // noop
  }
  
  
  /**
   * Visitor#visit_test_stmt()
   *
   * @param Node $node
   */
  public function visit_test_stmt($node) 
  {
    $this->visit($node->block);
  }
  
  /**
   * Visitor#visit_break_stmt()
   *
   * @param Node $node
   */
  public function visit_break_stmt($n) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_continue_stmt()
   *
   * @param Node $node
   */
  public function visit_continue_stmt($n) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_print_stmt()
   *
   * @param Node $node
   */
  public function visit_print_stmt($node) 
  {
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_throw_stmt()
   *
   * @param Node $node
   */
  public function visit_throw_stmt($node) 
  {
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_while_stmt()
   *
   * @param Node $node
   */
  public function visit_while_stmt($node) 
  {
    $this->visit($node->test);
    $this->visit($node->stmt);
  }
  
  /**
   * Visitor#visit_assert_stmt()
   *
   * @param Node $node
   */
  public function visit_assert_stmt($node) 
  {
    $this->visit($node->expr);  
    
    if ($node->message)
      $this->visit($node->message);  
  }
  
  /**
   * Visitor#visit_switch_stmt()
   *
   * @param Node $node
   */
  public function visit_switch_stmt($node) 
  {
    $this->visit($node->test);
    
    foreach ($node->cases as $case) {
      foreach ($case->labels as $idx => $label)
        if ($label->expr)
          $this->visit($label->expr);
      
      $this->visit($case->body);
    }    
  }
  
  /**
   * Visitor#visit_return_stmt()
   *
   * @param Node $node
   */
  public function visit_return_stmt($node) 
  {
    if ($node->expr)
      $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_expr_stmt()
   *
   * @param Node $node
   */
  public function visit_expr_stmt($node) 
  {
    $this->visit($node->expr);  
  }
  
  /**
   * Visitor#visit_paren_expr()
   *
   * @param Node $node
   */
  public function visit_paren_expr($node) 
  {
    $this->visit($node->expr);
    $this->reduce_paren_expr($node);
  }
  
  /**
   * Visitor#visit_tuple_expr()
   *
   * @param Node $node
   */
  public function visit_tuple_expr($node) 
  {
    foreach ($node->seq as $expr)
      $this->visit($expr);
    
    $this->reduce_tuple_expr($node);
  }
  
  /**
   * Visitor#visit_fn_expr()
   *
   * @param Node $node
   */
  public function visit_fn_expr($node) 
  {
    array_push($this->fnstk, $node->symbol);
    
    $this->visit_fn_params($node->params);
    $this->visit($node->body);
    
    array_pop($this->fnstk);
  }
  
  /**
   * Visitor#visit_bin_expr()
   *
   * @param Node $node
   */
  public function visit_bin_expr($node) 
  {
    $this->visit($node->left);
    $this->visit($node->right);  
    $this->reduce_bin_expr($node);
  }
  
  /**
   * Visitor#visit_check_expr()
   *
   * @param Node $node
   */
  public function visit_check_expr($node) 
  {
    $this->visit($node->left);
    $this->reduce_check_expr($node);
  }
  
  /**
   * Visitor#visit_cast_expr()
   *
   * @param Node $node
   */
  public function visit_cast_expr($node) 
  {
    $this->visit($node->expr);
    $this->reduce_cast_expr($node);
  }
  
  /**
   * Visitor#visit_update_expr()
   *
   * @param Node $node
   */
  public function visit_update_expr($node) 
  {
    $this->visit($node->expr);
    $this->reduce_update_expr($node);
  }
  
  /**
   * Visitor#visit_assign_expr()
   *
   * @param Node $node
   */
  public function visit_assign_expr($node) 
  {
    $this->visit($node->left);
    $this->visit($node->right);
    $this->reduce_assign_expr($node);
  }
  
  /**
   * Visitor#visit_member_expr()
   *
   * @param Node $node
   */
  public function visit_member_expr($node) 
  {
    $this->visit($node->object);
    
    if ($node->computed)
      $this->visit($node->member);
    
    $this->reduce_member_expr($node);
  }
  
  /**
   * Visitor#visit_offset_expr()
   *
   * @param Node $node
   */
  public function visit_offset_expr($node) 
  {
    $this->visit($node->object);
    $this->visit($node->offset);
    $this->reduce_offset_expr($node);
  }
  
  /**
   * Visitor#visit_cond_expr()
   *
   * @param Node $node
   */
  public function visit_cond_expr($node) 
  {
    $this->visit($node->test);
    
    if ($node->then)
      $this->visit($node->then);
    
    $this->visit($node->els);
    $this->reduce_cond_expr($node); 
  }
  
  /**
   * Visitor#visit_call_expr()
   *
   * @param Node $node
   */
  public function visit_call_expr($node) 
  {
    $this->visit($node->callee);
    $this->visit_fn_args($node->args);
    $this->reduce_call_expr($node);
  }
  
  /**
   * Visitor#visit_yield_expr()
   *
   * @param Node $node
   */
  public function visit_yield_expr($node) 
  {
    if ($node->key) 
      $this->visit($node->key);
    
    $this->visit($node->arg);
    $this->reduce_yield_expr($node);
  }
  
  /**
   * Visitor#visit_unary_expr()
   *
   * @param Node $node
   */
  public function visit_unary_expr($node) 
  {
    $this->visit($node->expr);
    $this->reduce_unary_expr($node); 
  }
  
  /**
   * Visitor#visit_new_expr()
   *
   * @param Node $node
   */
  public function visit_new_expr($node) 
  {
    $this->visit($node->name);
    $this->visit_fn_args($node->args);    
    $this->reduce_new_expr($node);
  }
  
  /**
   * Visitor#visit_del_expr()
   *
   * @param Node $node
   */
  public function visit_del_expr($node) 
  {
    $this->visit($node->expr);
    $this->reduce_del_expr($node); 
  }
  
  /**
   * Visitor#visit_lnum_lit()
   *
   * @param Node $node
   */
  public function visit_lnum_lit($node) 
  {
    $this->reduce_lnum_lit($node);
  }
  
  /**
   * Visitor#visit_dnum_lit()
   *
   * @param Node $node
   */
  public function visit_dnum_lit($node) 
  {
    $this->reduce_dnum_lit($node);
  }
  
  /**
   * Visitor#visit_snum_lit()
   *
   * @param Node $node
   */
  public function visit_snum_lit($node) 
  {
    assert(0);
  }
  
  /**
   * Visitor#visit_regexp_lit()
   *
   * @param Node $node
   */
  public function visit_regexp_lit($node) 
  {
    $this->reduce_regexp_lit($node);
  }
  
  /**
   * Visitor#visit_arr_gen()
   *
   * @param Node $node
   */
  public function visit_arr_gen($node) 
  {
    $this->visit($node->expr);
    $this->visit($node->init);
    $this->visit($node->each);
    $this->reduce_arr_gen($node);
  }
  
  /**
   * Visitor#visit_arr_lit()
   *
   * @param Node $node
   */
  public function visit_arr_lit($node) 
  {
    if ($node->items)
      foreach ($node->items as $item)
        $this->visit($item);
        
    $this->reduce_arr_lit($node);  
  }
  
  /**
   * Visitor#visit_obj_lit()
   *
   * @param Node $node
   */
  public function visit_obj_lit($node) 
  {
    if ($node->pairs)
      foreach ($node->pairs as $pair) {
        if ($pair->key instanceof ObjKey)
          $this->visit($pair->key->expr);
        else
          $this->visit($pair->key);
        
        $this->visit($pair->arg);
      }
      
    $this->reduce_obj_lit($node);
  }
  
  /**
   * Visitor#visit_name()
   *
   * @param Node $node
   */
  public function visit_name($node) 
  {
    $this->reduce_name($node);
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param Node $node
   */
  public function visit_ident($node) 
  {
    $this->reduce_ident($node);
  }
  
  /**
   * Visitor#visit_this_expr()
   *
   * @param Node $node
   */
  public function visit_this_expr($n) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_super_expr()
   *
   * @param Node $node
   */
  public function visit_super_expr($n) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_null_lit()
   *
   * @param Node $node
   */
  public function visit_null_lit($node) 
  {
    $this->reduce_null_lit($node);
  }
  
  /**
   * Visitor#visit_true_lit()
   *
   * @param Node $node
   */
  public function visit_true_lit($node) 
  {
    $this->reduce_true_lit($node);
  }
  
  /**
   * Visitor#visit_false_lit()
   *
   * @param Node $node
   */
  public function visit_false_lit($node) 
  {
    $this->reduce_false_lit($node);
  }
  
  /**
   * Visitor#visit_str_lit()
   *
   * @param Node $node
   */
  public function visit_str_lit($node) 
  {
    if (!empty ($node->parts))
      foreach ($node->parts as $part)
        $this->visit($part);
      
    $this->reduce_str_lit($node);
  }
  
  /**
   * Visitor#visit_kstr_lit()
   *
   * @param Node $node
   */
  public function visit_kstr_lit($node) 
  {
    $this->reduce_kstr_lit($node);
  }
  
  /**
   * Visitor#visit_type_id()
   *
   * @param Node $node
   */
  public function visit_type_id($n) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_engine_const()
   *
   * @param Node $node
   */
  public function visit_engine_const($node)
  {
    $this->reduce_engine_const($node);
  }
}
