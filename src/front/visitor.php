<?php

namespace phs\front;

use phs\Logger;
use phs\front\ast\Node;

/** generic visitor */
abstract class Visitor
{
  /**
   * constructor
   */
  public function __construct()
  {
    // empty
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
    elseif (is_array($node))
      foreach ($node as $item)
        $this->visit($item);
    
    // walk node
    elseif ($node instanceof Node) {
      $kind = $node->kind();
      $this->{'visit_' . $kind}($node); 
    }
    
    // error
    else {
      Logger::error('don\'t know how to traverse item \\');
      
      ob_start();
      var_dump($node);
      $log = ob_get_clean();
      
      Logger::error(substr($log, 0, 500));
    }
  }
  
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
  public function visit_member_attr($n) {}
  public function visit_trait_decl($n) {}
  public function visit_iface_decl($n) {}
  public function visit_fn_decl($n) {}
  public function visit_attr_decl($n) {}
  public function visit_topex_attr($n) {}
  public function visit_comp_attr($n) {}
  public function visit_var_decl($n) {}
  public function visit_use_decl($n) {}
  public function visit_require_decl($n) {}
  public function visit_label_decl($n) {}
  public function visit_alias_decl($n) {}
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
  public function visit_null_lit($n) {}
  public function visit_true_lit($n) {}
  public function visit_false_lit($n) {}
  public function visit_engine_const($n) {}
  public function visit_str_lit($n) {}
  public function visit_type_id($n) {}
}
