<?php

namespace phs\ast;

class EngineConst extends Expr
{
  public $type;
  
  public function __construct($type)
  {
    $this->type = $type;
  }
}
