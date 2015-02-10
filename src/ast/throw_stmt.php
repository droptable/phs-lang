<?php

namespace phs\ast;

use phs\Location;

class ThrowStmt extends Stmt
{
  // @var Expr
  public $expr;
  
  /**
   * constructor
   *
   * @param Location $Loc
   * @param Expr     $expr
   */
  public function __construct(Location $Loc, Expr $expr)
  {
    parent::__construct($loc);
    $this->expr = $expr;
  }

  public function __clone()
  {
    $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
