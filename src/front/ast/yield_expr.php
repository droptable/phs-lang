<?php

namespace phs\front\ast;

class YieldExpr extends Expr
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
