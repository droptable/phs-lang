<?php

namespace phs\ast;

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
