<?php

namespace phs\front;

require_once 'utils.php';
require_once 'scope.php';

use phs\Logger;
use phs\Session;

use phs\front\ast\Expr;
use phs\front\ast\Name;
use phs\front\ast\Ident;

/** lookup trait */
trait Lookup
{
  /**
   * lookup a value from a expression or symbol
   *
   * @param  Node $node
   * @return Value
   */
  private function lookup_value($node)
  {
    if ($node instanceof Expr)
      return $node->value;
    
    if (($node instanceof Name ||
         $node instanceof Ident) && $node->symbol)
      return $node->symbol->value;
    
    return Value::$UNDEF;
  }
  
  /**
   * lookup a name
   *
   * @param  Node $node
   * @return void
   */
  private function lookup_name($node)
  {
    $this->lookup_path($node, $node->root, name_to_arr($node));
  }
  
  /**
   * lookup a ident
   *
   * @param  Node $node
   * @return void
   */
  private function lookup_ident($node)
  {
    $this->lookup_path($node, false, [ ident_to_str($node) ]);
  }
  
  /**
   * lookup a path
   *
   * @param  Node $node
   * @param  boolean $root
   * @param  array $path
   * @return void
   */
  private function lookup_path($node, $root, $path)
  {
    assert(!empty ($path));
    
    $scope = $this->scope;
    
    if ($root === true)
      $scope = $this->sroot;
    
    $len = count($path);
    $sym = null;
    $ref = end($path);
    
    if ($len === 1) 
      $sym = $scope->get($path[0]);
    else {
      $mod = $scope;
      
      for ($i = 0, $l = $len - 1; $i < $l; ++$i) {
        $sub = $mod->mmap->get($path[$i]);
        
        if (!$sub) {
          if ($i > 0)
            Logger::error_at($node->loc, 'module `%s` has no sub-module `%s`', $mod->id, $path[$i]);
          else
            Logger::error_at($node->loc, 'module `%s` not found', $path[$i]);
          
          return; 
        }
        
        $mod = $sub;
      }
      
      $sym = $mod->get($path[$len - 1]);
    }
    
    // check if symbol exists
    if ($sym === null)
      Logger::error_at($node->loc, 'reference to undefined symbol `%s`', $ref);
    
    // check access
    elseif ($this->acc === ACC_READ && $sym->kind === SYM_KIND_VAR && 
            (!$sym->value || $sym->value->kind === VAL_KIND_NONE))
      Logger::warn_at($node->loc, 'reading uninitialized value of `%s`', $ref);
       
    $node->symbol = $sym;
  }
}

trait Convert
{
  /**
   * converts the given value to a string
   *
   * @param  Value  $val
   * @return boolean
   */
  private function convert_to_str(Value $val)
  {
    if ($val->kind === VAL_KIND_STRING)
      return true;
    
    $data = $val->data;
    
    switch ($val->kind) {
      case VAL_KIND_FLOAT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
      case VAL_KIND_LIST:
      case VAL_KIND_DICT:
        $data = (string) $data;
        break;
        
      case VAL_KIND_NULL: 
        $data = ''; 
        break;
      
      case VAL_KIND_TUPLE:
      case VAL_KIND_NEW:
      case VAL_KIND_UNDEF:
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
  private function convert_to_int(Value $val)
  {
    if ($val->kind === VAL_KIND_INT)
      return true;
    
    $data = $val->data;
    $okay = true;
    
    switch ($val->kind) {
      case VAL_KIND_FLOAT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
      case VAL_KIND_TUPLE:
        $data = (int) $data;
        break;
        
      case VAL_KIND_NULL: 
        $data = 0; 
        break;
      
      case VAL_KIND_LIST:
        $data = $data->size() ? 1 : 0;
        break;
        
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
  private function convert_to_float(Value $val)
  {
    if ($val->kind === VAL_KIND_FLOAT)
      return true;
    
    $data = $val->data;
    
    switch ($val->kind) {
      case VAL_KIND_INT:
      case VAL_KIND_BOOL:
      case VAL_KIND_STRING:
      case VAL_KIND_TUPLE:
        $data = (float) $data;
        break;
        
      case VAL_KIND_NULL: 
        $data = 0.0; 
        break;
      
      case VAL_KIND_LIST:
        $data = $data->size() > 1 ? 1 : 0;
        break;
        
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
  private function convert_to_num(Value $val)
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
  private function convert_to_bool(Value $val)
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
        $data = $data->size() > 0;
        break;
      
      case VAL_KIND_TUPLE:
        $data = !empty ($data);
        break;
        
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
}

/** expression reduce */
trait Reduce
{
  // mixin lookup-methods
  use Lookup;
  
  // mixin convert-methods
  use Convert;
  
  /**
   * reduces a paren_expr
   *
   * @param Node $node
   */
  public function reduce_paren_expr($node) 
  {
    $node->value = $this->lookup_value($node->expr);
  }
  
  /**
   * reduces a tuple_expr
   *
   * @param Node $node
   */
  public function reduce_tuple_expr($node) 
  {
    if (!$node->seq)
      $node->value = new Value(VAL_KIND_TUPLE, []);
    else {
      $okay = true;
      $node->value = Value::$UNDEF;
      
      foreach ($node->seq as $item)
        if ($this->lookup_value($item)->kind === VAL_KIND_UNDEF) {
          $okay = false;
          break;
        }
        
      if ($okay) {
        $tupl = [];
        
        foreach ($node->seq as $item)
          $tupl[] = $this->lookup_value($item);
        
        $node->value = new Value(VAL_KIND_TUPLE, $tupl);
      }
    }  
  }
  
  /**
   * reduces a fn_expr
   *
   * @param Node $node
   */
  public function reduce_fn_expr($n) {}
  
  /**
   * reduces a bin_expr
   *
   * @param Node $node
   */
  public function reduce_bin_expr($node)
  {    
    $lval = $this->lookup_value($node->left);
    $rval = $this->lookup_value($node->right);
    
    if ($lval->kind === VAL_KIND_UNDEF ||
        $rval->kind === VAL_KIND_UNDEF)
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
    
    $lval = clone $lval;
    $rval = clone $rval;
    
    $out = Value::$UNDEF;
    
    if ($this->convert_to_num($lval) &&
        $this->convert_to_num($rval)) {
      $data = 0;
      $kind = $kinds[(int) ($lval->kind === VAL_KIND_FLOAT ||
                            $rval->kind === VAL_KIND_FLOAT)];
      
      $lhs = $lval->data;
      $rhs = $rval->data;
      
      switch ($node->op->type) {
        case T_PLUS:  $data = $lhs + $rhs; break;
        case T_MINUS: $data = $lhs - $rhs; break;
        case T_MUL:   $data = $lhs * $rhs; break;
        case T_POW:   $data = pow($lhs, $rhs); break;
        
        case T_DIV: 
        case T_MOD:
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
            case T_DIV: $data = $lhs / $rhs; break;
            case T_MOD: $data = $lhs % $rhs; break;
            default: assert(0);
          }
          
          break;
        default: assert(0);
      }
    
      $out = new Value($kind, $data);
    }
    
    unset ($lval);
    unset ($rval);
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
    $lval = clone $lval;
    $rval = clone $rval;
    
    $out = Value::$UNDEF;
    
    if ($this->convert_to_int($lval) &&
        $this->convert_to_int($rval)) {
      $data = 0;
    
      $lhs = $lval->data;
      $rhs = $rval->data;
      
      switch ($node->op->type) {
        case T_BIT_XOR: $data = $lhs ^ $rhs; break;
        case T_BIT_AND: $data = $lhs & $rhs; break;
        case T_BIT_OR:  $data = $lhs | $rhs; break;
        case T_SL:      $data = $lhs << $rhs; break;
        case T_SR:      $data = $lhs >> $rhs; break;
        default: assert(0);
      }
      
      $out = new Value(VAL_KIND_INT, $data);
    }
    
    unset ($lval);
    unset ($rval);
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
        $lval = clone $lval;
        $rval = clone $rval;
        
        $out = Value::$UNDEF;
        
        if ($this->convert_to_num($lval) &&
            $this->convert_to_num($rval)) {
          $lhs = $lval->data;
          $rhs = $rval->data;
          
          switch ($node->op) {
            case T_GT:  $data = $lhs > $rhs; break;
            case T_LT:  $data = $lhs < $rhs; break;
            case T_GTE: $data = $lhs >= $rhs; break;
            case T_LTR: $data = $lhs <= $rhs; break;
            default: assert(0);
          }
          
          $out = new Value(VAL_KIND_BOOL, $data);
        }
        
        unset ($lval);
        unset ($rval);
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
    $lval = clone $lval;
    $rval = clone $rval;
    
    $out = Value::$UNDEF;
    
    if ($this->convert_to_bool($lval) &&
        $this->convert_to_bool($rval)) {
      $lhs = $lval->data;
      $rhs = $rval->data;
      $data = false;
      
      switch ($node->op->type) {
        case T_BOOL_AND: $data = $lhs && $rhs; break;
        case T_BOOL_OR:  $data = $lhs || $rhs; break;
          
        case T_BOOL_XOR:        
          $data = ($lhs && !$rhs) || (!$lhs && $rhs);
          break;
          
        default: assert(0);
      }
      
      $out = new Value(VAL_KIND_BOOL, $data);      
    }  
    
    unset ($lval);
    unset ($rval);
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
    $lval = clone $lval;
    $rval = clone $rval;
    
    $out = Value::$UNDEF;
    
    if ($this->convert_to_str($lval) &&
        $this->convert_to_str($rval))
      $out = new Value(VAL_KIND_STR, $lval->data . $rval->data);
    
    unset ($lval);
    unset ($rval);
    return $out;
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
  public function reduce_assign_expr($n) {}
  
  /**
   * reduces a member_expr
   *
   * @param Node $node
   */
  public function reduce_member_expr($n) {}
  
  /**
   * reduces a offset_expr
   *
   * @param Node $node
   */
  public function reduce_offset_expr($node) 
  {
    $obj = $this->lookup_value($node->object);
    $off = $this->lookup_value($node->offset);
    
    if ($obj->kind === VAL_KIND_UNDEF ||
        $off->kind === VAL_KIND_UNDEF)
      $node->value = Value::$UNDEF;
    else
      // check object
      if ($obj->kind !== VAL_KIND_LIST &&
          $obj->kind !== VAL_KIND_TUPLE &&
          $obj->kind !== VAL_KIND_STR) {
        Logger::error_at($node->object->loc, 'illegal offset left-hand-side');
        $node->value = Value::$UNDEF;
        
      // check offset
      } elseif ($off->kind !== VAL_KIND_INT) {
        Logger::error_at($node->offset->loc, 'illegal offset type');
        $node->value = Value::$UNDEF;
      
      // reduce
      } else 
        $node->value = $obj->data[$off->data];  
  }
  
  /**
   * reduces a cond_expr
   *
   * @param Node $node
   */
  public function reduce_cond_expr($n) {}
  
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
  public function reduce_yield_expr($n) {}
  
  /**
   * reduces a unary_expr
   *
   * @param Node $node
   */
  public function reduce_unary_expr($n) {}
  
  /**
   * reduces a new_expr
   *
   * @param Node $node
   */
  public function reduce_new_expr($n) {}
  
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
    $node->value = new Value(VAL_KIND_INT, $node->data);
  }
  
  /**
   * reduces a dnum_lit
   *
   * @param Node $node
   */
  public function reduce_dnum_lit($node) 
  {
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
    if (!$node->items)
      $node->value = new Value(VAL_KIND_LIST, new BuiltInList);
    else {
      $okay = true;
      $node->value = Value::$UNDEF;
      
      foreach ($node->items as $item)
        if ($this->lookup_value($item)->kind === VAL_KIND_UNDEF) {
          $okay = false;
          break;
        }
        
      if ($okay) {
        $list = new BuiltInList;
        
        foreach ($node->items as $item)
          $list[] = $this->lookup_value($item);
        
        $node->value = new Value(VAL_KIND_LIST, $list);
      }
    }  
  }
  
  /**
   * reduces a obj_lit
   *
   * @param Node $node
   */
  public function reduce_obj_lit($n) {}
  
  /**
   * reduces a name
   *
   * @param Node $node
   */
  public function reduce_name($node) 
  {
    $this->lookup_name($node);
  }
  
  /**
   * reduces a ident
   *
   * @param Node $node
   */
  public function reduce_ident($node) 
  {
    $this->lookup_ident($node);
  }
  
  /**
   * reduces a this_expr
   *
   * @param Node $node
   */
  public function reduce_this_expr($n) {}
  
  /**
   * reduces a super_expr
   *
   * @param Node $node
   */
  public function reduce_super_expr($n) {}
  
  /**
   * reduces a null_lit
   *
   * @param Node $node
   */
  public function reduce_null_lit($node) 
  {
    $node->value = new Value(VAL_KIND_NULL);
  }
  
  /**
   * reduces a true_lit
   *
   * @param Node $node
   */
  public function reduce_true_lit($node) 
  {
    $node->value = new Value(VAL_KIND_BOOL, true);
  }
  
  /**
   * reduces a false_lit
   *
   * @param Node $node
   */
  public function reduce_false_lit($node) 
  {
    $node->value = new Value(VAL_KIND_BOOL, false);
  }
  
  /**
   * reduces a engine_const
   *
   * @param Node $node
   */
  public function reduce_engine_const($n) {}
  
  /**
   * reduces a str_lit
   *
   * @param Node $node
   */
  public function reduce_str_lit($node) 
  {
    if (empty ($node->parts)) {
      $out = new Value(VAL_KIND_STR, $node->data);
      goto out;
    }
    
    $hcf = $node->flag === 'c';
    $lst = [ $node->data ];
    $lhs = 0;
    
    $out = Value::$UNDEF;
    $okay = true;
    $part = null;
    
    foreach ($node->parts as $idx => &$part) {
      if ($idx & 1) {
        if ($lhs === -1) {
          $lhs = array_push($lst, $part->data) - 1;
          continue;
        }
        
        $lst[$lhs] .= $part->data;
        $part = null;
        continue;
      }
      
      $val = $this->lookup_value($part);
      
      if ($val->kind === VAL_KIND_UNDEF)
        goto nxt;
      
      $val = clone $val;
        
      if ($this->convert_to_str($val)) {
        $lst[$lhs] .= $rhs->data;
        unset ($val);
        continue;
      }
        
      unset ($val);
      
      nxt:
      if ($hcf) goto err;
      
      array_push($lst, $part);
      $lhs = -1;
    }
    
    $node->data = array_shift($lst);
    $node->parts = $lst;
      
    if (empty ($node->parts)) {
      $node->parts = null;
      $out = new Value(VAL_KIND_STR, $node->data);
    }
    
    goto out;
    
    err:
    Logger::error_at($part->loc, 'constant string-substitution must be convertable to a string value');
    
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
    $node->value = new Value(VAL_KIND_STR, $node->data);
  }
  
  /**
   * reduces a type_id
   *
   * @param Node $node
   */
  public function reduce_type_id($n) {} 
}
