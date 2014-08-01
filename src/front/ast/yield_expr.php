<?php

namespace phs\front\ast;

class YieldExpr extends Expr
{
  public $key;
  public $value;
  
  public function __construct($key, $value)
  {
    $this->key = $key;
    $this->value = $value;
  }
}
