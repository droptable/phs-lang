<?php

namespace phs\front\ast;

class UnaryExpr extends Expr
{
  public $op;
  public $expr;
  
  public function __construct($op, $expr)
  {
    $this->op = $op;
    $this->expr = $expr;
  }
}
