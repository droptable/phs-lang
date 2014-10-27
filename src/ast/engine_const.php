<?php

namespace phs\ast;

class EngineConst extends Expr
{
  public $type;
  
  // @var Symbol  if bound to a symbol
  public $symbol;
  
  public function __construct($type)
  {
    $this->type = $type;
  }
}
