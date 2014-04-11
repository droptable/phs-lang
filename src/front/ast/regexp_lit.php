<?php

namespace phs\front\ast;

class RegexpLit extends Expr
{
  public $value;
  
  public function __construct($value)
  {
    $this->value = $value;
  }
}
