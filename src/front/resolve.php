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

use phs\front\ast\Name;
use phs\front\ast\Ident;
use phs\front\ast\UseAlias;
use phs\front\ast\UseUnpack;

/** unit resolver */
class UnitResolver extends Visitor
{
  // @var Session
  private $sess;
  
  // @var Scope
  private $scope;
  
  // @var Scope
  private $sroot;
    
  // @var Walker
  private $walker;
  
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
    
    $this->walker = new Walker($this);
    $this->walker->walk_some($unit);
  }
  
  /**
   * reduces a node to a value
   *
   * @param  Node $node
   * @return Value
   */
  protected function reduce_expr($node)
  {
    $red = new Reducer($this->sess, $this->scope);
    $val = $red->reduce($node);
    return $val;
  }
  
  /* ------------------------------------ */
    
  public function visit_unit($node)
  {
    $this->walker->walk_some($node->body);
  }
  
  public function walk_module($node)
  {
    // todo: don't save the module-scope at the ast-node
    $prev = $this->scope;
    $this->scope = $node->scope;
    $this->walker->walk_some($node->body);
    $this->scope = $prev;
  }
  
  public function visit_content($node)
  {
    $this->walker->walk_some($node->body);
  }
  
  public function visit_var_decl($node)
  {
    $flags = mods_to_sym_flags($node->mods);
    
    foreach ($node->vars as $var) {
      $sym = VarSymbol::from($var, $flags);
      $sym->value = $this->reduce($var->init);
      $this->scope->add($sym);
    }
  }
  
  public function visit_require_decl($node)
  {
    $path = $this->reduce_expr($node->expr);
    
    if ($path->kind !== VAL_KIND_STRING)
      Logger::error_at($node->expr->loc, '`require` path does not reduce to a string constant');
    else {
      
    }
  }
  
  
}
