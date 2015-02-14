<?php

namespace phs\ast;

use phs\Location;

class ForStmt extends Stmt
{
  // @var VarDecl|Expr  initializer
  public $init;
  
  // @var Expr  condition
  public $test;
  
  // @var Expr  update
  public $each;
  
  // @var Stmt  inner statements
  public $stmt;
  
  // @var Scope own scope
  public $scope;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Node     $init
   * @param Expr     $test
   * @param Expr     $each
   * @param Stmt     $stmt
   */
  public function __construct(Location $loc, Node $init = null, 
                              Expr $test = null, Expr $each = null, 
                              Stmt $stmt)
  {
    parent::__construct($loc);
    
    $this->init = $init;
    $this->test = $test;
    $this->each = $each;
    $this->stmt = $stmt;
  }

  public function __clone()
  {
    $this->init = clone $this->init;
    $this->test = clone $this->test;
    $this->each = clone $this->each;
    $this->stmt = clone $this->stmt;
    
    if ($this->scope)
      $this->scope = clone $this->scope;
    
    parent::__clone();
  }
}
