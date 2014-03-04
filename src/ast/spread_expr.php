<?php

namespace phs\ast;

class SpreadExpr extends Expr
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
