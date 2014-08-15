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

use phs\lang\BuiltInList;
use phs\lang\BuiltInDict;

const
  ACC_READ = 1,
  ACC_WRITE = 2
;

/** 
 * unit resolver
 * 
 * - folds constant expressions (without optimizations)
 * - executes <require>
 * - executes <use>
 */
class UnitResolver extends Visitor
{
  // mixin reduce, convert and lookup-methods
  use Reduce;
  
  // @var Session
  private $sess;
  
  // @var Scope
  private $scope;
  
  // @var Scope
  private $sroot;
  
  // @var Reducer
  private $redc;
    
  // @var int
  private $acc = ACC_READ;
  
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
    $this->scope = new UnitBranch($scope);
    $this->sroot = $this->scope;
    
    $this->visit($unit);
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
    $prev = $this->scope;
    $this->scope = new ModuleBranch($node->scope);
    $this->visit($node->body);
    $this->scope = $prev;
  }
   
  /**
   * Visitor#visit_block()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_block($node) 
  { 
    // block gets a own scope for functions and enums
    if (!$node->scope)
      $node->scope = new Scope($this->scope);
    
    $prev = $this->scope;
    $this->scope = new Branch($node->scope);
    $this->visit($node->body);
    $this->scope = $prev;
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
  public function visit_nested_mods($node) 
  {
    // all nested-mods should be gone after the desugarer
    assert(0);
  }
  
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
  public function visit_fn_decl($node) 
  {
    if (!$node->scope)
      $node->scope = new Scope($this->scope);
    
    $prev = $this->scope;
    $this->scope = new Branch($node->scope);
    $this->visit($node->body);
    $this->scope = $prev;
  }
  
  /**
   * Visitor#visit_attr_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_attr_decl($node) 
  {
    // TODO
  }
  
  /**
   * Visitor#visit_topex_attr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_topex_attr($node) 
  {
    $this->visit($node->topex); 
  }
  
  /**
   * Visitor#visit_comp_attr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_comp_attr($node) 
  {
    $this->visit($node->comp);
  }
  
  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_var_decl($node) 
  {
    $flags = mods_to_sym_flags($node->mods);
    
    foreach ($node->vars as $var) {
      $sym = VarSymbol::from($var, $flags);
      
      if ($var->init) {
        $this->visit($var->init);
        $val = $this->lookup_value($var->init);
        
        if ($flags & SYM_FLAG_CONST && (!$val || $val->kind === VAL_KIND_UNDEF))
          Logger::error_at($var->loc, 'constant variable initializer must be reducible to a constant value');
        
        $sym->value = $val;
      } else
        $sym->value = Value::$NONE;
      
      $this->scope->add($sym);
    }  
  }
  
  /**
   * Visitor#visit_use_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_use_decl($node) 
  {
    // TODO
  }
  
  /**
   * Visitor#visit_require_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_require_decl($node) 
  {
    $this->visit($node->expr);
    $this->reduce_require_decl($node);
  }
  
  /**
   * Visitor#visit_label_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_label_decl($node) 
  {
    $this->visit($node->stmt); 
  }
  
  /**
   * Visitor#visit_alias_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_alias_decl($node) 
  {
    // TODO
  }
  
  /**
   * Visitor#visit_do_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_do_stmt($node) 
  {
    $this->visit($node->stmt);
    $this->visit($node->test);
  }
  
  /**
   * Visitor#visit_if_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_if_stmt($node) 
  {
    $this->visit($node->test);
    $this->visit($node->stmt);
    
    if ($node->elsifs) {
      foreach ($node->elsifs as $elsif) {
        $this->inject_block($elsif);
        $this->visit($elsif->test);
        $this->visit($elsif->stmt);
      }
    }
    
    if ($node->els) {
      $this->inject_block($node->els);
      $this->visit($node->els->stmt);
    }
  }
  
  /**
   * Visitor#visit_for_stmt()
   *
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
   */
  public function visit_for_in_stmt($node) 
  {
    // TODO: key:value must be added as variables
    $this->visit($node->rhs);
    $this->visit($node->stmt);
  }
  
  /**
   * Visitor#visit_try_stmt()
   *
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
   */
  public function visit_php_stmt($node) 
  {
    // TODO
  }
  
  /**
   * Visitor#visit_goto_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_goto_stmt($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_test_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_test_stmt($node) 
  {
    $this->visit($node->block);
  }
  
  /**
   * Visitor#visit_break_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_break_stmt($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_continue_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_continue_stmt($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_print_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_print_stmt($node) 
  {
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_throw_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_throw_stmt($node) 
  {
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_while_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_while_stmt($node) 
  {
    $this->visit($node->test);
    $this->visit($node->stmt);
  }
  
  /**
   * Visitor#visit_assert_stmt()
   *
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
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
   * @param  Node  $node
   * @return void
   */
  public function visit_return_stmt($node) 
  {
    if ($node->expr)
      $this->visit($node->expr);
  }
  
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
    $prev = $this->scope;
    $this->scope = new Branch($node->scope);
    $this->visit($node->body);
    $this->scope = $prev;
    
    $this->reduce_fn_expr($node);
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
    $this->reduce_check_expr($node);
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
    $this->reduce_cast_expr($node);
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
    $this->reduce_update_expr($node);
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
    
    $this->reduce_call_expr($node);
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
    $this->reduce_yield_expr($node);
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
    $this->reduce_lnum_lit($node);
  }
  
  /**
   * Visitor#visit_dnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dnum_lit($node) 
  {
    $this->reduce_dnum_lit($node);
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
    $this->reduce_regexp_lit($node);
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
    $this->reduce_arr_gen($node);
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
    $this->reduce_name($node); 
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ident($node) 
  {
    $this->reduce_ident($node);  
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
    $this->reduce_null_lit($node);
  }
  
  /**
   * Visitor#visit_true_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_true_lit($node) 
  {
    $this->reduce_true_lit($node); 
  }
  
  /**
   * Visitor#visit_false_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_false_lit($node) 
  {
    $this->reduce_false_lit($node); 
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
    if ($node->parts)
      foreach ($node->parts as $idx => $part)
        if ((~$idx) & 1) $this->visit($part);
      
    $this->reduce_str_lit($node);
  }
  
  /**
   * Visitor#visit_kstr_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_kstr_lit($node) 
  {
    $this->reduce_kstr_lit($node);
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
}
