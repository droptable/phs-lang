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

  public function __clone()
  {
    $this->expr = clone $this->expr;
    $this->type = clone $this->type;
    
    parent::__clone();
  }
}
