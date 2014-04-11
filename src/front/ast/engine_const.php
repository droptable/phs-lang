<?php

namespace phs\front\ast;

class EngineConst extends Expr
{
  public $type;
  
  public function __construct($type)
  {
    $this->type = $type;
  }
}
