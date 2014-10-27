<?php

namespace phs\ast;

class ThisExpr extends Expr
{
  // @var Symbol  resolved symbol
  public $symbol;
  
  public function __clone()
  {
    $this->symbol = null;
    
    parent::__clone();
  }
}
