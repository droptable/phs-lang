<?php

namespace phs\ast;

class UnaryExpr extends Expr
{
  public $op;
  public $expr;
  
  public function __construct($op, $expr)
  {
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
