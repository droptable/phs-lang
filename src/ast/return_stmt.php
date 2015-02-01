<?php

namespace phs\ast;

use phs\Location;

class ReturnStmt extends Stmt
{
  // @var Expr
  public $expr;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param Expr     $expr
   */
  public function __construct(Location $loc, Expr $expr)
  {
    parent::__construct($loc);
    $this->expr = $expr;
  }

  public function __clone()
  {
    if ($this->expr)
      $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
