<?php

namespace phs\front\ast;

class YieldExpr extends Expr
{
  public $key;
  public $arg;
  
  public function __construct($key, $arg)
  {
    $this->key = $key;
    $this->arg = $arg;
  }
}
