<?php

namespace phs\front\ast;

class ParenExpr extends Expr
{
  public $expr;
  
  public function __construct($expr)
  {
    $this->expr = $expr;
  }
}