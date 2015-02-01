<?php

namespace phs\ast;

use phs\Symbol;
use phs\Location;

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
