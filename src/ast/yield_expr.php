<?php

namespace phs\ast;

class YieldExpr extends Expr
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
