<?php

namespace phs\ast;

use phs\Location;

class WhileStmt extends Stmt
{
  // @var Expr
  public $test;
  
  // @var Stmt
  public $stmt;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $test
   * @param Stmt     $stmt
   */
  public function __construct(Location $loc, Expr $test, Stmt $stmt)
  {
    parent::__construct($loc);
    
    $this->test = $test;
    $this->stmt = $stmt;
  }

  public function __clone()
  {
    $this->test = clone $this->test;
    $this->stmt = clone $this->stmt;
    
    parent::__clone();
  }
}
