<?php

namespace phs\ast;

class PrintExpr extends Expr
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
