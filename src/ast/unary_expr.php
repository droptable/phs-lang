<?php

namespace phs\ast;

use phs\Token;
use phs\Location;

class UnaryExpr extends Expr
{
  // @var int
  public $op;
  
  // @var Expr
  public $expr;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param int      $op
   * @param Expr     $expr
   */
  public function __construct(Location $loc, $op, Expr $expr)
  {
    parent::__construct($loc);
    
    $this->op = $op;
    $this->expr = $expr;
  }

  public function __clone()
  {
    $this->op = clone $this->op;
    $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
