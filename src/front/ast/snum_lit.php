<?php

namespace phs\front\ast;

class SNumLit extends Expr
{
  public $value;
  public $suffix;
  
  public function __construct($value, $suffix)
  {
    $this->value = $value;
    $this->suffix = $suffix;
  }
}
