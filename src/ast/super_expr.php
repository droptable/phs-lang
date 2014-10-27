<?php

namespace phs\ast;

class SuperExpr extends Expr
{
  // @var Symbol  resolved symbol
  public $symbol;
  
  public function __clone()
  {
    $this->symbol = null;
    
    parent::__clone();
  }
}
