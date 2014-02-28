#!/usr/bin/php
<?php

require '../src/source.php';
require '../src/context.php';
require '../src/lexer.php';
require '../src/parser.php';

use phs\Parser;
use phs\Context;
use phs\Analyzer;
use phs\FileSource;

$ctx = new Context;
$psr = new Parser($ctx);
$std = $psr->parse_source(new FileSource(realpath(__DIR__ . '/../lib/std.phs')));
$ast = $psr->parse_source(new FileSource(realpath(__DIR__ . '/test.phs')));

if (!$ast) exit('failed');

// var_dump($ast);
// exit;
require '../src/walker.php';
require '../src/analyzer.php';

$wlk = new Analyzer($ctx);
$wlk->analyze($std);
$wlk->analyze($ast);

print "modules:\n";
print $ctx->get_module()->debug();

print "\n";
print "global scope:\n";
print $ctx->get_scope()->debug();

/* ------------------------------------ */

class TestWalker extends phs\Walker
{
  private $ctx;
  
  public function __construct(phs\Context $ctx)
  {
    parent::__construct($ctx);
    $this->ctx = $ctx;
  }
  
  protected function enter_unit($n) { print "enter unit\n";}
  protected function leave_unit($n) { print "leave unit\n";}
  
  protected function enter_module($n) { print "enter module\n";}
  protected function leave_module($n) { print "leave module\n";}
  
  protected function enter_program($n) { print "enter program\n";}
  protected function leave_program($n) { print "leave program\n";}
  
  // no enter/leave since it can not be nested
  protected function visit_enum_decl($n) { print "visit enum\n";}
  
  protected function enter_class_decl($n) { print "enter class\n";}
  protected function leave_class_decl($n) { print "leave class\n";}
  
  protected function enter_nested_mods($n) { print "enter nested\n";}
  protected function leave_nested_mods($n) { print "leave nested\n";}
  
  protected function enter_ctor_decl($n) { print "enter ctor\n";}
  protected function leave_ctor_decl($n) { print "leave ctor\n";}
  
  protected function enter_dtor_decl($n) { print "enter dtor\n";}
  protected function leave_dtor_decl($n) { print "leave dtor\n";}
  
  protected function enter_trait_decl($n) { print "enter trait\n";}
  protected function leave_trait_decl($n) { print "leave trait\n";}
  
  protected function enter_iface_decl($n) { print "enter iface\n";}
  protected function leave_iface_decl($n) { print "leave iface\n";}
  
  protected function enter_block($n) { print "enter block\n";}
  protected function leave_block($n) { print "leave block\n";}
  
  protected function visit_let_decl($n) { print "visit let\n";}
  protected function visit_var_decl($n) { print "visit var\n";}
  protected function visit_use_decl($n) { print "visit use\n";}
  protected function visit_require_decl($n) { print "visit require\n";}
  
  protected function visit_do_stmt($n) { print "visit do\n";}
  protected function visit_if_stmt($n) { print "visit if\n";}
  protected function visit_for_stmt($n) { print "visit for\n";}
  protected function visit_for_in_stmt($n) { print "visit for\n";}
  protected function visit_try_stmt($n) { print "visit try\n";}
  protected function visit_php_stmt($n) { print "visit php\n";}
  protected function visit_goto_stmt($n) { print "visit goto\n";}
  protected function visit_test_stmt($n) { print "visit test\n";}
  protected function visit_break_stmt($n) { print "visit break\n";}
  protected function visit_continue_stmt($n) { print "visit continue\n";}
  protected function visit_throw_stmt($n) { print "visit throw\n";}
  protected function visit_while_stmt($n) { print "visit while\n";}
  protected function visit_yield_stmt($n) { print "visit yield\n";}
  protected function visit_assert_stmt($n) { print "visit assert\n";}
  protected function visit_switch_stmt($n) { print "visit switch\n";}
  protected function visit_return_stmt($n) { print "visit return\n";}
  protected function visit_labeled_stmt($n) { print "visit labeled\n";}
  protected function visit_expr_stmt($n) { print "visit expr\n";}
  
  protected function enter_fn_decl($node) { print "enter fn " . $node->id->value . "\n"; }
  protected function leave_fn_decl($node) { print "leave fn " . $node->id->value . "\n"; }
}
