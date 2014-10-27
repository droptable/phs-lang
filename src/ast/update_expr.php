<?php

namespace phs\ast;

class UpdateExpr extends Expr
{
  public $prefix;
  public $expr;
  public $op;
  
  public function __construct($prefix, $expr, $op)
  {
    $this->prefix = $prefix;
    $this->expr = $expr;
    $this->op = $op;
  }

  public function __clone()
  {
    $this->expr = clone $this->expr;
    $this->op = clone $this->op;
    
    parent::__clone();
  }
}
