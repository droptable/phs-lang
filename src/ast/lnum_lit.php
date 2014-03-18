<?php

namespace phs\ast;

class LNumLit extends Expr
{
  public $value;
  
  public function __construct($value)
  {
    $this->value = $value;
  }
}
