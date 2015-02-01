<?php

namespace phs\ast;

use phs\Symbol;
use phs\Location;

class SelfExpr extends Expr
{
  // @var Symbol
  public $symbol;
  
  public function __clone()
  {
    $this->symbol = null;
    
    parent::__clone();
  }
}
