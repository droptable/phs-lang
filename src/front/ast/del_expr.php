<?php

namespace phs\front\ast;

class DelExpr extends Expr
{
  public $id;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}
