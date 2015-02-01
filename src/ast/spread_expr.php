<?php

namespace phs\ast;

use phs\Location;

class SpreadExpr extends Expr
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
    $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
