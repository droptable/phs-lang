<?php

namespace phs\ast;

use phs\Location;

class UpdateExpr extends Expr
{
  // @var bool  prefix or postfix
  public $prefix;
  
  // @var Expr
  public $expr;
  
  // @var int
  public $op;
  
  /**
   * constructor
   *
   * @param Location $loc
   * @param bool     $prefix
   * @param int      $op
   * @param Expr     $expr
   */
  public function __construct(Location $loc, $prefix, $op, Expr $expr)
  {
    parent::__construct($loc);
    
    $this->prefix = $prefix;
    $this->op = $op;
    $this->expr = $expr;
  }

  public function __clone()
  {
    $this->expr = clone $this->expr;
    $this->op = clone $this->op;
    
    parent::__clone();
  }
}
