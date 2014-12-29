<?php

namespace phs\ast;

use phs\Location;

class DoStmt extends Stmt
{
  // @var Stmt  inner statements
  public $stmt;
  
  // @var Expr  condition
  public $test;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Stmt     $stmt
   * @param Expr     $test
   */
  public function __construct(Location $loc, Stmt $stmt, Expr $test)
  {
    parent::__construct($loc);
    
    $this->stmt = $stmt;
    $this->test = $test;
  }

  public function __clone()
  {
    $this->stmt = clone $this->stmt;
    $this->test = clone $this->test;
    
    parent::__clone();
  }
}
