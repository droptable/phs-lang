<?php

namespace phs;

require_once 'glob.php';
require_once 'parser.php';
require_once 'visitor.php';

use phs\ast\Unit;
use phs\ast\Name;
use phs\ast\Block;
use phs\ast\Param;
use phs\ast\ThisParam;
use phs\ast\Ident;
use phs\ast\NewExpr;
use phs\ast\ThisExpr;
use phs\ast\MemberExpr;
use phs\ast\AssignExpr;
use phs\ast\CallExpr;
use phs\ast\UnaryExpr;
use phs\ast\ExprStmt;
use phs\ast\ReturnStmt;
use phs\ast\ObjKey;
use phs\ast\NamedArg;
use phs\ast\RestArg;
use phs\ast\NestedMods;
use phs\ast\StrLit;

class DesugarTask extends Visitor implements Task
{
  // @var Session
  private $sess;
  
  // @var array 
  private $nmods = [];
  
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
   * desugar
   *
   * @param  Unit   $unit
   * @return void
   */
  public function run(Unit $unit)
  {
    $this->visit($unit);
    gc_collect_cycles();
  }
  
  /**
   * injects a block where a statement is expected.
   * 
   * in:
   *   if(1) fun(); 
   *   
   * out:
   *   if(1) { fun(); }
   *
   * @param  Node $node
   * @return void
   */
  private function inject_block($node)
  {
    $body = $node->stmt;
    
    if (!($body instanceof Block)) {
      // remove empty-expression
      if ($body instanceof ExprStmt && $body->expr === null)
        $body = [];
      else
        $body = [ $body ];
      
      $node->stmt = $this->mixin_loc(new Block($body));
    }
  }
  
  /**
   * injects a block+return statement for "=>" functions
   *
   * @param  Node $node
   * @return void
   */
  private function inject_return($node)
  {
    $body = $node->body;
    
    // abstract/extern -> do not modifiy
    if ($body === null) return;
    
    if (!($body instanceof Block))
      $node->body = $this->mixin_loc(new Block([ new ReturnStmt($body) ]));
  }
  
  /**
   * handles members and merges modifiers from nested-mods
   * and removes nested-mods nodes
   * 
   * @param  array $members
   * @return void
   */
  private function handle_members(&$members)
  {
    $stack = [];
    
    foreach ($members as $idx => &$member) {
      if ($member instanceof NestedMods) {
        if ($member->members) {
          array_push($this->nmods, $member->mods);
          $this->handle_members($member->members);
          array_pop($this->nmods);
        }
        
        array_splice($members, $idx, 1);
        
        if ($member->members)
          foreach ($member->members as $child)
            // pushing directly to $members results in undefined behavior
            $stack[] = $child;
          
        // remove modifier-references from node
        // to make it a bit easier for the gc:
        // 1.) to collect the nested-mods node after desugaring
        // 2.) to collect the modifier-nodes if they get removed in a later pass
        if ($member->mods) 
          foreach ($member->mods as $idx => $_)
            unset ($member->mods[$idx]);
          
        unset ($member->mods);
        
      } else {
        $this->merge_mods($member);
        $this->visit($member);
      }
    }
    
    // push array of elements without abusing call_user_func_array()
    array_splice($members, count($members), 0, $stack);
  }
  
  /**
   * merges modifiers from nested-mods
   *
   * @param  Node $member
   * @return void
   */
  private function merge_mods($member)
  {
    if (!$this->nmods) return;
    
    if (!$member->mods)
      $member->mods = [];
    
    $mods = &$member->mods;
    
    // adding from top to bottom for proper error-reporting
    for ($i = count($this->nmods) - 1; $i >= 0; --$i) {
      $nm = &$this->nmods[$i];
      
      for ($o = count($nm) - 1; $o >= 0; --$o)
        array_unshift($mods, $nm[$o]);
    }
  }
  
  /**
   * gives a node a "location" pointing to a invalid position.
   * this identifies generated nodes.
   *
   * @param  Node $node
   * @return Node
   */
  private function mixin_loc($node) 
  {
    $node->loc = new Location('{generated code}',
      new Position(0, 0));
    
    return $node;
  }
  
  /* ------------------------------------ */
  
  /**
   * Visitor#visit_enum_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_enum_decl($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_class_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_class_decl($node) 
  {
    if ($node->members)
      $this->handle_members($node->members);
  }
  
  /**
   * Visitor#visit_nested_mods()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_nested_mods($node) 
  {
    // "nested-mods" nodes should be completely removed from the tree.
    assert(0);
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
    $this->inject_return($node);
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
    $this->inject_return($node);
    $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_member_attr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_member_attr($node) 
  {
    $this->visit($node->member);
  }
  
  /**
   * Visitor#visit_trait_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_trait_decl($node) 
  {
    if ($node->members)
      $this->handle_members($node->members);
  }
  
  /**
   * Visitor#visit_iface_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_iface_decl($node) 
  {
    if ($node->members)
      $this->handle_members($node->members);
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
    $this->inject_return($node);
    $this->visit($node->body);
  }
  
  /**
   * Visitor#visit_attr_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_attr_decl($node) 
  {
    // noop
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
   * Visitor#visit_use_decl()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_use_decl($node) 
  {
    // noop
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
    $this->inject_block($node);  
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
    $this->inject_block($node);
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
    $this->inject_block($node);
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
    $this->inject_block($node);
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
    // noop  
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
    $expr = &$node->expr;
    
    // rewrite uncommon expressions to ::phs::ex(expr)
    // phs::ex is a small wrapper-function for exceptions at runtime
    if (!($expr instanceof Name ||
          $expr instanceof Ident ||
          $expr instanceof NewExpr ||
          $expr instanceof CallExpr)) {
      $name = new Name(new Ident('phs'), true);
      $name->add(new Ident('ex'));
      $expr = new CallExpr($name, [ ($expr) ]);
    }
    
    $this->visit($expr);
  }
  
  /**
   * Visitor#visit_while_stmt()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_while_stmt($node) 
  {
    $this->inject_block($node);
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
    $this->inject_return($node);
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
   * Visitor#visit_lnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_lnum_lit($node) 
  {
    // noop
  }
  /**
   * Visitor#visit_dnum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_dnum_lit($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_snum_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_snum_lit($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_regexp_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_regexp_lit($node) 
  {
    // noop
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
   * Visitor#visit_name()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_name($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_ident()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_ident($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_this_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_this_expr($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_super_expr()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_super_expr($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_null_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_null_lit($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_true_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_true_lit($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_false_lit()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_false_lit($node) 
  {
    // noop
  }
  
  /**
   * Visitor#visit_engine_const()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_engine_const($node) 
  {
    // noop
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
  
  /**
   * Visitor#visit_kstr_lit()
   *
   * @param  Node $node
   * @return void
   */
  public function visit_kstr_lit($node)
  {
    // noop
  }
  
  /**
   * Visitor#visit_type_id()
   *
   * @param  Node  $node
   * @return void
   */
  public function visit_type_id($node) 
  {
    // noop
  }
}
