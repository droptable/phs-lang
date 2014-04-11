<?php

namespace phs\front\ast;

class DNumLit extends Expr
{
  public $value;
  
  public function __construct($value)
  {
    $this->value = $value;
  }
}
