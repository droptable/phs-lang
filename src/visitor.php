<?php

namespace phs;

use phs\ast\Node;
use phs\ast\NamedArg;
use phs\ast\RestArg;
use phs\ast\Param;
use phs\ast\ObjKey;

/** generic visitor */
abstract class Visitor
{
  // @var Location  last location
  private $lloc = null;
  
  /**
   * constructor
   */
  public function __construct()
  {
    // empty
  }
  
  /**
   * returns the last visited location
   *
   * @return Location
   */
  public function last_loc() 
  {
    return $this->lloc;
  }
  
  /**
   * visits a node/array-of-nodes
   *
   * @param  Node|array $node
   * @return void
   */
  public function visit($node)
  {
    // nothing?
    if ($node === null)
      return; // leave early
          
    // walk array-of-nodes
    if (is_array($node))
      foreach ($node as $item)
        $this->visit($item);
    
    // walk node
    elseif ($node instanceof Node) {
      $m = 'visit_' . $node->kind();
      if (!method_exists($this, $m)) {
        echo "\nunable to walk: $m\n";
        assert(0);
      }
      assert(method_exists($this, $m));
      if ($node->loc) $this->lloc = $node->loc;
      $this->{$m}($node); 
    }
    
    // error
    else {
      Logger::error('don\'t know how to traverse item \\');
      
      ob_start();
      var_dump($node);
      $log = ob_get_clean();
      
      Logger::error(substr($log, 0, 500));
      
      if ($this->lloc !== null)
        Logger::info_at($this->lloc, 'last visited location was here');
      
      // abort
      echo "\n";
      assert(0);
    }
  }
  
  /* ------------------------------------ */
  
  /**
   * utility: visits function arguments
   *
   * @param  array $args
   * @return void
   */
  public function visit_fn_args($args)
  {
    if (!$args) return;
    
    foreach ($args as $arg)
      if ($arg instanceof NamedArg ||
          $arg instanceof RestArg)
        $this->visit($arg->expr);
      else
        $this->visit($arg);
  }
  
  /**
   * utility: visits function parameters
   *
   * @param  array $params
   * @return void
   */
  public function visit_fn_params($params)
  {
    if (!$params) return;
    
    foreach ($params as $param)
      if ($param instanceof Param && $param->init)
        $this->visit($param->init);
  }
  
  /* ------------------------------------ */
  
  // each method is a noop by default, override what you need.
  
  public function visit_unit($n) { $this->visit($n->body); }
  public function visit_module($n) { $this->visit($n->body); }
  public function visit_content($n) { $this->visit($n->body); } 
  public function visit_block($n) { $this->visit($n->body); }
  
  public function visit_enum_decl($n) {}
  public function visit_class_decl($n) {}
  public function visit_nested_mods($n) {}
  public function visit_ctor_decl($n) {}
  public function visit_dtor_decl($n) {}
  public function visit_getter_decl($n) {}
  public function visit_setter_decl($n) {}
  public function visit_trait_decl($n) {}
  public function visit_iface_decl($n) {}
  public function visit_fn_decl($n) {}
  public function visit_var_decl($n) {}
  public function visit_var_list($n) {}
  public function visit_use_decl($n) {}
  public function visit_require_decl($n) {}
  public function visit_label_decl($n) {}
  public function visit_do_stmt($n) {}
  public function visit_if_stmt($n) {}
  public function visit_for_stmt($n) {}
  public function visit_for_in_stmt($n) {}
  public function visit_try_stmt($n) {}
  public function visit_php_stmt($n) {}
  public function visit_goto_stmt($n) {}
  public function visit_test_stmt($n) {}
  public function visit_break_stmt($n) {}
  public function visit_continue_stmt($n) {}
  public function visit_print_stmt($n) {}
  public function visit_throw_stmt($n) {}
  public function visit_while_stmt($n) {}
  public function visit_assert_stmt($n) {}
  public function visit_switch_stmt($n) {}
  public function visit_return_stmt($n) {}
  public function visit_expr_stmt($n) {}
  public function visit_paren_expr($n) {}
  public function visit_tuple_expr($n) {}
  public function visit_fn_expr($n) {}
  public function visit_bin_expr($n) {}
  public function visit_check_expr($n) {}
  public function visit_cast_expr($n) {}
  public function visit_update_expr($n) {}
  public function visit_assign_expr($n) {}
  public function visit_member_expr($n) {}
  public function visit_offset_expr($n) {}
  public function visit_cond_expr($n) {}
  public function visit_call_expr($n) {}
  public function visit_yield_expr($n) {}
  public function visit_unary_expr($n) {}
  public function visit_new_expr($n) {}
  public function visit_del_expr($n) {}
  public function visit_lnum_lit($n) {}
  public function visit_dnum_lit($n) {}
  public function visit_snum_lit($n) {}
  public function visit_regexp_lit($n) {}
  public function visit_arr_gen($n) {}
  public function visit_arr_lit($n) {}
  public function visit_obj_lit($n) {}
  public function visit_name($n) {}
  public function visit_ident($n) {}
  public function visit_this_expr($n) {}
  public function visit_super_expr($n) {}
  public function visit_self_expr($n) {}
  public function visit_null_lit($n) {}
  public function visit_true_lit($n) {}
  public function visit_false_lit($n) {}
  public function visit_str_lit($n) {}
  public function visit_kstr_lit($n) {}
  public function visit_type_id($n) {}
  public function visit_engine_const($n) {}
}

/** automatic-visitor: visits _all_ nodes */
abstract class AutoVisitor extends Visitor
{
  // <unit>, <module>, <content> and <block> don't need extra code.
  // <use> is excluded, because visiting without any logic behind doesn't makes sense
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node  $node
   * @return void
   */ 
  public function visit_enum_decl($node) 
  {
    foreach ($node->vars as $var)
      if ($var->init)
        $this->visit($var->init); 
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_class_decl($node) 
  {
    // only members gonna be visited here
    if ($node->members)
      foreach ($node->members as $member)
        $this->visit($member);  
  }
  
  /**
   * Visitor#visit_nested_mods()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_nested_mods($node) 
  {
    if ($node->members)
      foreach ($node->members as $member)
        $this->visit($member);  
  }
  
  /**
   * Visitor#visit_ctor_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ctor_decl($node) 
  {
    $this->visit_fn_params($node->params);
    
    if ($node->body)
      $this->visit($node->body);  
  }
  
  /**
   * Visitor#visit_dtor_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dtor_decl($node) 
  {
    $this->visit_fn_params($node->params);
    
    if ($node->body)
      $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_getter_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_getter_decl($node) 
  {
    $this->visit_fn_params($node->params);
    
    if ($node->body)
      $this->visit($node->body);   
  }
  
  /**
   * Visitor#visit_setter_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_setter_decl($node) 
  {
    $this->visit_fn_params($node->params);
    
    if ($node->body)
      $this->visit($node->body); 
  }
  
  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_trait_decl($node) 
  {
    // only members gonna be visited here
    if ($node->members)
      foreach ($node->members as $member)
        $this->visit($member);  
  }
  
  /**
   * Visitor#visit_iface_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_iface_decl($node) 
  {
    // only members gonna be visited here
    if ($node->members)
      foreach ($node->members as $member)
        $this->visit($member);
  }
  
  /**
   * Visitor#visit_fn_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_fn_decl($node) 
  {
    $this->visit_fn_params($node->params);
    
    if ($node->body)
      $this->visit($node->body);
  }
    
  /**
   * Visitor#visit_var_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_var_decl($node) 
  {
    foreach ($node->vars as $var)
      if ($var->init)
        $this->visit($var->init);  
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
  public function visit_label_decl($node) 
  {
    $this->visit($node->stmt); 
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
   * @param  Node $node
   * @return void
   */
  public function visit_paren_expr($node)
  {
    $this->visit($node->expr);
  }
  
  /**
   * Visitor#visit_tuple_expr()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_tuple_expr($node)
  {
    $this->visit($node->seq);
  }
  
  /**
   * Visitor#visit_fn_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_fn_expr($node) 
  {
    $this->visit_fn_params($node->params);
    $this->visit($node->body); 
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
    $this->visit($node->right); 
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
    $this->visit($node->type); 
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
    
    // only visit member on computed expressions
    if ($node->computed)
      $this->visit($node->member);
  }
  
  /**
   * Visitor#visit_offset_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_offset_expr($node) 
  {
    $this->visit($node->object);
    $this->visit($node->offset);
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
    $this->visit_fn_args($node->args);  
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
      
    $this->visit($node->arg);  
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
    $this->visit_fn_args($node->args);  
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
  }
  
  /**
   * Visitor#visit_arr_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_arr_lit($node)
  {
    if (!$node->items)
      return;
    
    foreach ($node->items as $item)
      $this->visit($item);
  }
  
  /**
   * Visitor#visit_obj_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_obj_lit($node)
  {
    if (!$node->pairs)
      return;
    
    foreach ($node->pairs as $pair) {
      if ($pair->key instanceof ObjKey)
        $this->visit($pair->key->expr);
      
      $this->visit($pair->arg);
    }
  }
  
  /**
   * Visitor#visit_str_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_str_lit($node) 
  {
    // raw string or no interpolation
    if ($node->flag === 'r' || empty ($node->parts))
      return;
    
    foreach ($node->parts as $part)
      $this->visit($part);
  }
}
