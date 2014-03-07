<?php

namespace phs;

use phs\ast\Node;
use phs\ast\Unit;

/** ast walker - texas ranger */
abstract class Walker
{
  // context
  private $ctx;
  
  // nesting level
  private $level;
  
  // stop-indicator
  private $stop;
  
  // drop indicator
  private $drop;
  
  /**
   * constructor
   * 
   * @param Context $ctx
   */
  public function __construct(Context $ctx)
  {
    $this->ctx = $ctx;
    $this->level = 0;
  }
  
  /**
   * returns the current nesting level
   * 
   * @return int
   */
  public function level()
  {
    return $this->level;
  }
  
  /** 
   * start the walker
   * 
   * @param  Unit   $unit
   */
  public function walk(Unit $node)
  {
    $this->stop = false;
    $this->drop = false;
    $this->walk_some($node);
  }
  
  /**
   * tells the walker that the current node was dropped
   * 
   */
  public function drop()
  {
    $this->drop = true;
  }
  
  /**
   * tell the walker to stop as soon as possible
   * (escaping a recursion is not that easy)
   *
   */
  public function stop()
  {
    $this->stop = true;
  }
  
  /**
   * check if the walker was stopped
   * 
   * @return boolean
   */
  public function stopped()
  {
    return $this->stop;
  }
  
  /**
   * walks a node or an array
   * 
   * @param  Node|array $node
   */
  protected function walk_some($some)
  {
    if ($this->stop || $some === null)
      return;
    
    if (is_array($some)) {
      foreach ($some as $item)
        $this->walk_some($item);
      return;
    } 
    
    if ($some instanceof Node) {
      $this->walk_node($some);
      return;
    }
    
    $this->ctx->error(ERR_ERROR, COM_WLK, 'don\'t know how to traverse item');
    
    ob_start();
    var_dump($some);
    $log = ob_get_clean();
    
    print substr($log, 0, 500) . '...';
  }
  
  /**
   * walks a node
   * 
   * @param  Node $node
   */
  protected function walk_node(Node $node) 
  {
    if ($this->stop) return;
    
    $kind = $node->kind();
    
    switch ($kind) {
      case 'unit':
      case 'module':
      case 'program':
      case 'fn_decl':
      case 'block';
      case 'ctor_decl':
      case 'dtor_decl':
      case 'getter_decl':
      case 'setter_decl':
        $this->enter_node($kind, $node, $node->body);
        break;
      case 'class_decl':
      case 'iface_decl':
      case 'trait_decl':
      case 'nested_mods':
        $this->enter_node($kind, $node, $node->members);
        break;
      default:
        $this->visit_node($kind, $node);
    }
  }
  
  /* ------------------------------------ */
  
  private function enter_node($kind, Node $node, $body)
  {
    if ($this->stop) return;
    
    $this->{"enter_$kind"}($node);
    
    if (!$this->drop) {
      ++$this->level;
      
      $this->walk_some($body);
      
      $this->{"leave_$kind"}($node);
      --$this->level;
    }
    
    // reset
    $this->drop = false;
  }
  
  private function visit_node($kind, Node $node)
  {
    if ($this->stop) return;
    
    $this->{"visit_$kind"}($node);
  }
  
  /* ------------------------------------ */
  /* override what you need */
  
  protected function enter_unit($n) {}
  protected function leave_unit($n) {}
  
  protected function enter_module($n) {}
  protected function leave_module($n) {}
  
  protected function enter_program($n) {}
  protected function leave_program($n) {}
  
  // no enter/leave since it can not be nested
  protected function visit_enum_decl($n) {}
  
  protected function enter_class_decl($n) {}
  protected function leave_class_decl($n) {}
  
  protected function enter_nested_mods($n) {}
  protected function leave_nested_mods($n) {}
  
  protected function enter_ctor_decl($n) {}
  protected function leave_ctor_decl($n) {}
  
  protected function enter_dtor_decl($n) {}
  protected function leave_dtor_decl($n) {}
  
  protected function enter_getter_decl($n) {}
  protected function leave_getter_decl($n) {}
  
  protected function enter_setter_decl($n) {}
  protected function leave_setter_decl($n) {}
  
  protected function enter_trait_decl($n) {}
  protected function leave_trait_decl($n) {}
  
  protected function enter_iface_decl($n) {}
  protected function leave_iface_decl($n) {}
  
  protected function enter_fn_decl($n) {}
  protected function leave_fn_decl($n) {}
  
  protected function visit_topex_attr_decl($n) {}
  protected function visit_comp_attr_decl($n) {}
  protected function visit_attr_decl($n) {}
  
  protected function enter_block($n) {}
  protected function leave_block($n) {}
  
  protected function visit_let_decl($n) {}
  protected function visit_var_decl($n) {}
  protected function visit_use_decl($n) {}
  protected function visit_require_decl($n) {}
  
  protected function visit_do_stmt($n) {}
  protected function visit_if_stmt($n) {}
  protected function visit_for_stmt($n) {}
  protected function visit_for_in_stmt($n) {}
  protected function visit_try_stmt($n) {}
  protected function visit_php_stmt($n) {}
  protected function visit_goto_stmt($n) {}
  protected function visit_test_stmt($n) {}
  protected function visit_break_stmt($n) {}
  protected function visit_continue_stmt($n) {}
  protected function visit_throw_stmt($n) {}
  protected function visit_while_stmt($n) {}
  protected function visit_yield_stmt($n) {}
  protected function visit_assert_stmt($n) {}
  protected function visit_switch_stmt($n) {}
  protected function visit_return_stmt($n) {}
  protected function visit_labeled_stmt($n) {}
  protected function visit_expr_stmt($n) {}
  
  protected function visit_fn_expr($n) {}
  protected function visit_bin_expr($n) {}
  protected function visit_check_expr($n) {}
  protected function visit_cast_expr($n) {}
  protected function visit_update_expr($n) {}
  protected function visit_assign_expr($n) {}
  protected function visit_member_expr($n) {}
  protected function visit_cond_expr($n) {}
  protected function visit_call_expr($n) {}
  protected function visit_yield_expr($n) {}
  protected function visit_unary_expr($n) {}
  protected function visit_new_expr($n) {}
  protected function visit_del_expr($n) {}
  
  protected function visit_lnum_lit($n) {}
  protected function visit_dnum_lit($n) {}
  protected function visit_snum_lit($n) {}
  
  protected function visit_regexp_lit($n) {}
  
  protected function visit_arr_lit($n) {}
  protected function visit_obj_lit($n) {}
  
  protected function visit_name($n) {}
  protected function visit_ident($n) {}
  
  protected function visit_this_expr($n) {}
  protected function visit_super_expr($n) {}
  protected function visit_null_lit($n) {}
  protected function visit_true_lit($n) {}
  protected function visit_false_lit($n) {}
  protected function visit_engine_const($n) {}
  
  protected function visit_str_lit($n) {}
  
  protected function visit_type_id($n) {}
}
