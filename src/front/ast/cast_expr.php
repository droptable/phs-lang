<?php

namespace phs\front\ast;

class CastExpr extends Expr
{
  public $expr;
  public $type;
  
  public function __construct($expr, $type)
  {
    $this->expr = $expr;
    $this->type = $type;
  }
}
