<?php

namespace phs\front;

require_once 'utils.php';
require_once 'visitor.php';
require_once 'symbols.php';
require_once 'values.php';
require_once 'scope.php';
require_once 'reduce.php';

use phs\Logger;
use phs\Session;

use phs\util\Set;
use phs\util\Map;
use phs\util\Cell;
use phs\util\Entry;

use phs\front\ast\Node;
use phs\front\ast\Unit;

use phs\front\ast\Expr;
use phs\front\ast\Name;
use phs\front\ast\Ident;
use phs\front\ast\UseAlias;
use phs\front\ast\UseUnpack;

use phs\lang\_List;
use phs\lang\_Dict;

const
  ACC_READ = 1,
  ACC_WRITE = 2
;

/** unit resolver */
class UnitResolver extends Visitor
{
  // @var Session
  private $sess;
  
  // @var Scope
  private $scope;
  
  // @var Scope
  private $sroot;
  
  // @var Reducer
  private $redc;
    
  // @var Value  used for constant-folding
  private $value;
  
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
   * resolver
   *
   * @param  UnitScope $scope
   * @param  Unit      $unit 
   * @return void
   */
  public function resolve(UnitScope $scope, Unit $unit)
  {
    $this->scope = $scope;
    $this->sroot = $this->scope;
    
    $this->visit($unit);
  }
  
  /* ------------------------------------ */
  
  /**
   * enters a node with a bound scope and body
   *
   * @param  Node $node
   * @return void
   */
  private function enter($node)
  {
    assert(!empty ($node->scope));
    assert(!empty ($node->body));
    
    $prev = $this->scope;
    $this->scope = $node->scope;
    $this->visit($node->body);
    $this->scope = $prev;
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_module()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_module($node) 
  { 
    $this->enter($node);
  }
   
  /**
   * Visitor#visit_block()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_block($node) 
  { 
    $this->enter($node);
  }
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_enum_decl($n) {}
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_class_decl($n) {}
  /**
   * Visitor#visit_nested_mods()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_nested_mods($n) {}
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ctor_decl($n) {}
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dtor_decl($n) {}
  /**
   * Visitor#visit_getter_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_getter_decl($n) {}
  /**
   * Visitor#visit_setter_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_setter_decl($n) {}
  /**
   * Visitor#visit_member_attr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_member_attr($n) {}  
  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_trait_decl($n) {}
  /**
   * Visitor#visit_iface_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_iface_decl($n) {}
  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_fn_decl($n) {}
  /**
   * Visitor#visit_attr_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_attr_decl($n) {}
  /**
   * Visitor#visit_topex_attr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_topex_attr($n) {}
  /**
   * Visitor#visit_comp_attr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_comp_attr($n) {}
  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_var_decl($n) {}
  /**
   * Visitor#visit_use_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_use_decl($n) {}
  /**
   * Visitor#visit_require_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_require_decl($node) 
  {
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_label_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_label_decl($n) {}
  /**
   * Visitor#visit_alias_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_alias_decl($n) {}
  /**
   * Visitor#visit_do_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_do_stmt($n) {}
  /**
   * Visitor#visit_if_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_if_stmt($n) {}
  /**
   * Visitor#visit_for_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_for_stmt($n) {}
  /**
   * Visitor#visit_for_in_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_for_in_stmt($n) {}
  /**
   * Visitor#visit_try_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_try_stmt($n) {}
  /**
   * Visitor#visit_php_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_php_stmt($n) {}
  /**
   * Visitor#visit_goto_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_goto_stmt($n) {}
  /**
   * Visitor#visit_test_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_test_stmt($n) {}
  /**
   * Visitor#visit_break_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_break_stmt($n) {}
  /**
   * Visitor#visit_continue_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_continue_stmt($n) {}
  /**
   * Visitor#visit_print_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_print_stmt($n) {}
  /**
   * Visitor#visit_throw_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_throw_stmt($n) {}
  /**
   * Visitor#visit_while_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_while_stmt($n) {}
  /**
   * Visitor#visit_assert_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_assert_stmt($n) {}
  /**
   * Visitor#visit_switch_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_switch_stmt($n) {}
  /**
   * Visitor#visit_return_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_return_stmt($n) {}
  
  /**
   * Visitor#visit_expr_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_expr_stmt($node) 
  {
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_paren_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_paren_expr($node) 
  {
    $this->visit($node->expr);
    $this->reduce_paren_expr($node);
  }
  
  /**
   * Visitor#visit_tuple_expr()
   *
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
   */
  public function visit_fn_expr($node) 
  {
    $node->value = FnValue($node);
    $this->enter($node);
  }
  
  /**
   * Visitor#visit_bin_expr()
   *
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
   */
  public function visit_check_expr($node) 
  {
    $this->visit($node->left);
    $node->value = Value::$UNDEF; // TODO
  }
  
  /**
   * Visitor#visit_cast_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_cast_expr($node) 
  {
    $this->visit($node->expr);
    $node->value = Value::$UNDEF; // TODO
  }
  
  /**
   * Visitor#visit_update_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_update_expr($node) 
  {
    $this->visit($node->expr);
    // TODO: check if expr is mutable!
    $node->value = Value::$UNDEF; // TODO
  }
  
  /**
   * Visitor#visit_assign_expr()
   *
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
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
   * @param  Node $node
   * @return void
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
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
   */
  public function visit_call_expr($node) 
  {
    $this->visit($node->callee);
    
    if ($node->args)
      foreach ($node->args as $arg)
        $this->visit($arg); 
    
    $node->value = Value::$UNDEF; // TODO 
  }
  
  /**
   * Visitor#visit_yield_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_yield_expr($node) 
  {
    if ($node->key) 
      $this->visit($node->key);
    
    $this->visit($node->value);
    $node->value = Value::$UNDEF;
  }
  
  /**
   * Visitor#visit_unary_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_unary_expr($node) 
  {
    $this->visit($node->expr);
    $this->reduce_unary_expr($node);  
  }
  
  /**
   * Visitor#visit_new_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_new_expr($node) 
  {
    $this->visit($node->name);
    
    if ($node->args)
      foreach ($node->args as $arg)
        $this->visit($arg);
    
    $this->reduce_new_expr($node);
  }
  
  /**
   * Visitor#visit_del_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_del_expr($node) 
  {
    $this->visit($node->expr);
    $this->reduce_del_expr($node);  
  }
  
  /**
   * Visitor#visit_lnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_lnum_lit($node) 
  {
    $node->value = new Value(VAL_KIND_INT, $node->data);  
  }
  
  /**
   * Visitor#visit_dnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dnum_lit($node) 
  {
    $node->value = new Value(VAL_KIND_FLOAT, $node->data);  
  }
  
  /**
   * Visitor#visit_snum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_snum_lit($node) 
  {
    throw new \RuntimeException('found a snum_lit');  
  }
  
  /**
   * Visitor#visit_regexp_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_regexp_lit($node) 
  {
    $node->value = new Value(VAL_KIND_REGEXP, $node->data);  
  }
  
  /**
   * Visitor#visit_arr_gen()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_arr_gen($node) 
  {
    $this->visit($node->expr);
    $this->visit($node->init);
    $this->visit($node->each);
    
    // it's a list, but has a unknown value
    $node->value = new Value(VAL_KIND_LIST, null);
  }
  
  /**
   * Visitor#visit_arr_lit()
   *
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
   */
  public function visit_obj_lit($node) 
  {
    if ($node->pairs)
      foreach ($node->pairs as $pair) {
        if ($pair->key instanceof ObjKey)
          $this->visit($pair->key->expr);
        else
          $this->visit($pair->key);
        
        $this->visit($pair->value);
      }
      
    $this->reduce_obj_lit($node);  
  }
  
  /**
   * Visitor#visit_name()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_name($node) 
  {
    $this->lookup_name($node);  
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ident($node) 
  {
    $this->lookup_ident($node);  
  }
  
  /**
   * Visitor#visit_this_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_this_expr($node) 
  {
    // TODO  
  }
  
  /**
   * Visitor#visit_super_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_super_expr($node) 
  {
    // TODO  
  }
  
  /**
   * Visitor#visit_null_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_null_lit($node) 
  {
    $node->value = new Value(VAL_KIND_NULL);  
  }
  
  /**
   * Visitor#visit_true_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_true_lit($node) 
  {
    $node->value = new Value(VAL_KIND_BOOL, true);  
  }
  
  /**
   * Visitor#visit_false_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_false_lit($node) 
  {
    $node->value = new Value(VAL_KIND_BOOL, false);  
  }
  
  /**
   * Visitor#visit_engine_const()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_engine_const($node) 
  {
    // TODO
  }
  
  /**
   * Visitor#visit_str_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_str_lit($node) 
  {    
    if ($node->parts) {
      foreach ($node->parts as $idx => $part)
        if ((~$idx) & 1) $this->visit($part);
      
      $out = Value::$UNDEF;
      
      if ($node->flag === 'c') {
        // join all parts now or die tryin'
        $okay = true;
        $data = $node->data;
        
        foreach ($node->parts as $idx => $part) {
          if ($idx & 1) {
            if ($okay)
              $data .= $part->data;
            
            continue;
          }
          
          $val = $this->lookup_value($part);
          
          if ($val === null) {
            $okay = false;
            continue;
          }     
          
          $val = clone $val;
               
          if (!$this->convert_to_str($val)) {
            Logger::error_at($part->loc, 'constant string-substitution must be convertable to a string value');
            $okay = false;
          } 
          
          elseif ($okay) 
            $data .= $val->data;
          
          unset ($val);
        }
        
        if ($okay)
          $out = new Value(VAL_KIND_STR, $data);
        
      } else {
        // try to join as much parts as possible
        $lst = [ $node->data ];
        $lhs = 0;
         
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
          
          if ($val !== null) {
            $val = clone $val;
            
            if ($this->convert_to_str($val)) {
              $lst[$lhs] .= $rhs->data;
              unset ($val);
              continue;
            }
            
            unset ($val);
          }
          
          array_push($lst, $part);
          $lhs = -1;
        }
        
        $node->data = array_shift($lst);
        $node->parts = $lst;
        
        if (empty ($node->parts)) {
          $node->parts = null;
          $out = new Value(VAL_KIND_STR, $node->data);
        }
      }
    } else
      $out = new Value(VAL_KIND_STR, $node->data);
      
    $node->value = $out;
  }
  
  /**
   * Visitor#visit_kstr_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_kstr_lit($node) 
  {
    $node->value = new Value(VAL_KIND_STR, $node->data);
  }
  
  /**
   * Visitor#visit_type_id()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_type_id($node) 
  {
    // TODO
  }
  
  /* ------------------------------------ */
  
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
    
    if ($node instanceof Name ||
        $node instanceof Ident) {
      /*
      $sym = $node->symbol;
      
      if ($sym !== null && $sym->kind === SYM_KIND_VAR &&
          ($sym->flags & SYM_FLAG_CONST || $this->branch->has($sym))) {
        if (!$sym->value || $sym->value->kind === VAL_KIND_NONE)
          Logger::error_at($node->loc, 'attempt to use uninitialized symbol');
        
        return $sym->value;
      }
      */
    }
    
    return null;
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
    
    if ($len === 1) 
      $sym = $scope->get($path[0]);
    else {
      $mod = $scope;
      
      for ($i = 0, $l = $len - 1; $i < $l; ++$i) {
        $sub = $mod->mmap->get($path[$i]);
        
        if (!$sub) {
          Logger::error_at($node->loc, 'module `%s` has no member `%s`', $mod->id, $path[$i]);
          return; 
        }
        
        $mod = $sub;
      }
      
      $sym = $mod->get($path[$len - 1]);
    }
    
    if ($sym === null)
      Logger::error_at($node->loc, 'reference to undefined symbol `%s`', end($path));
          
    $node->symbol = $sym;
  }
  
  /* ------------------------------------ */
  
  /**
   * reduces a offset expr to a value
   *
   * @param  Node $node
   * @return void
   */
  private function reduce_offset_expr($node)
  {
    $obj = $node->object->value;
    $off = $node->offset->value;
    
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
   * reduces a tuple to a value
   *
   * @param  Node $node
   * @return void
   */
  private function reduce_tuple_expr($node)
  {
    if (!$node->seq)
      $node->value = new Value(VAL_KIND_TUPLE, []);
    else {
      $okay = true;
      $node->value = Value::$UNDEF;
      
      foreach ($node->seq as $item)
        if ($item->value->kind === VAL_KIND_UNDEF) {
          $okay = false;
          break;
        }
        
      if ($okay) {
        $tupl = [];
        
        foreach ($node->seq as $item)
          $tupl[] = $item->value;
        
        $node->value = new Value(VAL_KIND_TUPLE, $tupl);
      }
    }
  }
  
  /**
   * reduces a array-literal to a value
   *
   * @param  Node $node
   * @return void
   */
  private function reduce_arr_lit($node)
  {
    if (!$node->items)
      $node->value = new Value(VAL_KIND_LIST, new _List);
    else {
      $okay = true;
      $node->value = Value::$UNDEF;
      
      foreach ($node->items as $item)
        if ($item->value->kind === VAL_KIND_UNDEF) {
          $okay = false;
          break;
        }
        
      if ($okay) {
        $list = new _List;
        
        foreach ($node->items as $item)
          $list[] = $item->value;
        
        $node->value = new Value(VAL_KIND_LIST, $list);
      }
    }
  }
  
  /**
   * reduces a bin-expr to a value
   *
   * @param  Node $node
   * @return void
   */
  private function reduce_bin_expr($node)
  {    
    if ($node->left->value === VAL_KIND_UNDEF ||
        $node->right->value === VAL_KIND_UNDEF)
      $node->value = Value::$UNDEF;
    else
      switch ($node->op->type) {
        // arithmetic 
        case T_PLUS: case T_MINUS: 
        case T_MUL: case T_DIV: 
        case T_MOD: case T_POW:
          $node->value = $this->reduce_arithmetic_op($node);
          break;
        // bitwise
        case T_BIT_XOR: case T_BIT_AND: 
        case T_BIT_OR: 
        case T_SL: case T_SR:
          $node->value = $this->reduce_bitwise_op($node);
          break;
        // logical
        case T_GT: case T_LT:  case T_GTE:
        case T_LTE: case T_EQ: case T_NEQ:
          $node->value = $this->reduce_logical_op($node);
          break;
        // boolean
        case T_BOOL_AND: case T_BOOL_OR: 
        case T_BOOL_XOR:
          $node->value = $this->reduce_boolean_op($node);
          break;
        case T_CONCAT:
          $node->value = $this->reduce_concat_op($node);
          break;
        // in/not-in and range
        case T_IN: case T_NIN:
          $node->value = $this->reduce_in_op($node);
          break;
        // range
        case T_RANGE:
          $node->value = $this->reduce_range_op($node);
          break;
        default:
          assert(0);
      }  
  }
  
  /**
   * reduces a arithmetic operation
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_arithmetic_op($node)
  {
    static $kinds = [ VAL_KIND_INT, VAL_KIND_FLOAT ];
    
    $lhs = clone $node->left->value;
    $rhs = clone $node->right->value;
    
    $out = Value::$UNDEF;
    
    if ($this->convert_to_num($lhs) &&
        $this->convert_to_num($rhs)) {
      // float > int
      $kind = $kinds[(int) ($lhs->kind === VAL_KIND_FLOAT ||
                            $rhs->kind === VAL_KIND_FLOAT)];
      
      $data = 0;
      $lval = $lhs->data;
      $rval = $rhs->data;
      
      switch ($node->op->type) {
        case T_PLUS: $data = $lval + $rval; break;
        case T_MINUS: $data = $lval - $rval; break;
        case T_MUL: $data = $lval * $rval; break;
        case T_POW: $data = pow($lval, $rval); break;
        
        case T_DIV: 
        case T_MOD:
          if ($rval == 0) { // "==" intended
            Logger::warn_at($node->loc, 'division by zero');
            
            // PHP: division by zero yields a boolean false
            $kind = VAL_KIND_BOOL;
            $data = false;
            break;
          }
          
          // PHP: division always results in a float
          $kind = VAL_KIND_FLOAT;
          
          switch ($node->op) {
            case T_DIV: $data = $lval / $rval; break;
            case T_MOD: $data = $lval % $rval; break;
            default: assert(0);
          }
          
          break;
        default: assert(0);
      }
    
      $out = new Value($kind, $data);
    }
    
    unset ($lhs);
    unset ($rhs);
    return $out;
  }
  
  /**
   * reduces a bitwise operation
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_bitwise_op($node)
  {
    $lhs = clone $node->left->value;
    $rhs = clone $node->right->value;
    
    $out = Value::$UNDEF;
    
    if ($this->convert_to_int($lhs) &&
        $this->convert_to_int($rhs)) {
      $data = 0;
      $lval = $lhs->data;
      $rval = $rhs->data;
      
      switch ($node->op->type) {
        case T_BIT_XOR: $data = $lval ^ $rval; break;
        case T_BIT_AND: $data = $lval & $rval; break;
        case T_BIT_OR: $data = $lval | $rval; break;
        case T_SL: $data = $lval << $rval; break;
        case T_SR: $data = $lval >> $rval; break;
        default: assert(0);
      }
      
      $out = new Value(VAL_KIND_INT, $data);
    }
    
    unset ($lhs);
    unset ($rhs);
    return $out;
  }
  
  /**
   * reduces a logical operation
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_logical_op($node)
  {
    $lhs = $node->left->value;
    $rhs = $node->right->value;
    
    $data = false;
    $lval = $lhs->data;
    $rval = $rhs->data;
    
    switch ($node->op->type) {
      case T_GT: case T_LT: 
      case T_GTE: case T_LTE:
        $lhs = clone $lhs;
        $rhs = clone $rhs;
        
        $out = Value::$UNDEF;
        
        if ($this->convert_to_num($lhs) &&
            $this->convert_to_num($rhs)) {
          $lval = $lhs->data;
          $rval = $rhs->data;
          
          switch ($node->op) {
            case T_GT:: $data = $lval > $rval; break;
            case T_LT: $data = $lval < $rval; break;
            case T_GTE: $data = $lval >= $rval; break;
            case T_LTR: $data = $lval <= $rval; break;
            default: assert(0);
          }
          
          $out = new Value(VAL_KIND_BOOL, $data);
        }
        
        unset ($lhs);
        unset ($rhs);
        return $out;
      
      case T_EQ: $data = $lval === $rval; break;
      case T_NEQ: $data = $lval !== $rval; break;
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
    $lhs = clone $node->left->value;
    $rhs = clone $node->right->value;
    
    $out = Value::$UNDEF;
    
    if ($this->convert_to_bool($lhs) &&
        $this->convert_to_bool($rhs)) {
      $lval = $lhs->data;
      $rval = $rhs->data;
      $data = false;
      
      switch ($node->op->type) {
        case T_BOOL_AND: $data = $lval && $rval; break;
        case T_BOOL_OR: $data = $lval || $rval; break;
          
        case T_BOOL_XOR:        
          $data = ($lval && !$rval) || (!$lval && $rval);
          break;
          
        default: assert(0);
      }
      
      $out = new Value(VAL_KIND_BOOL, $data);      
    }  
    
    unset ($lhs);
    unset ($rhs);
    return $out;
  }
  
  /**
   * reduces a string-concat operation
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_concat_op($node)
  {
    $lhs = clone $node->left->value;
    $rhs = clone $node->right->value;
    
    $out = Value::$UNDEF;
    
    if ($this->convert_to_str($lhs) &&
        $this->convert_to_str($rhs))
      $out = new Value(VAL_KIND_STR, $lhs->data . $rhs->data);
    
    unset ($lhs);
    unset ($rhs);
    return $out;
  }
  
  /* ------------------------------------ */
  
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
