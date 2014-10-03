<?php

namespace phs\front\ast;

class SelfExpr extends Expr
{
  // @var Symbol  resolved symbol
  public $symbol;
  
  public function __clone()
  {
    $this->symbol = null;
    
    parent::__clone();
  }
}
