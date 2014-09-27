<?php

namespace phs\front\ast;

class SpreadExpr extends Expr
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }

  public function __clone()
  {
    $this->expr = clone $this->expr;
    
    parent::__clone();
  }
}
