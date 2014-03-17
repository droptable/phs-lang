<?php

namespace phs;

require 'writer.php';

use phs\ast\Unit;

/** code generator (for now single target PHP 5.4) */
class Generator extends Walker
{
  // compiler context
  private $ctx;
  
  // output handle
  private $out;
  
  // start time
  private $start;
  
  // current scope
  private $scope;
  
  public function __construct(Context $ctx)
  {
    parent::__construct($ctx);
    $this->ctx = $ctx;
  }
  
  public function generate(Unit $unit)
  {
    // init writer
    $this->out = new FileWriter($unit->dest);
    $this->walk($unit);
  }
  
  /* ------------------------------------ */
  
  protected function emit($data)
  {
    $this->out->write($data);
  }
  
  protected function emitln($data)
  {
    $this->emit($data);
    $this->emit("\n");
  }
  
  /* ------------------------------------ */
  
  protected function enter_unit($node)
  {
    $this->scope = $node->scope;
    $this->start = microtime(true);
    $this->emitln('<?php');
    $this->emitln('/* generated by PHS */');
  }
  
  protected function leave_unit($node)
  {
    $this->emitln('');
    $this->emit('/* built: ');
    $this->emit(microtime(true) - $this->start);
    $this->emitln('s */');
  }
  
  protected function enter_fn_decl($node)
  {
    $fid = ident_to_str($node->id);
    $sym = $this->scope->get($fid, false, null, false);
    
    if (!$sym) assert(0);
    
    $this->emit('function ' . $fid . '(){}');
  }
  
  protected function visit_expr_stmt($node)
  {
    $this->walk_some($node->expr);
    $this->emit(';');
  }
  
  protected function visit_call_expr($node)
  {
    $this->walk_some($node->callee);
    $this->emit('(');
    $this->walk_some($node->args);
    $this->emit(')');
  }
  
  protected function visit_name($node)
  {
    $this->emit(name_to_str($node));
  }
}
