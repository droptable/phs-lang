<?php

namespace phs\front\ast;

abstract class Expr extends Node
{
  // if the expression is reducible at compile-time
  public $value;

  public function __clone()
  {
    if ($this->value)
      $this->value = clone $this->value;
    
    parent::__clone();
  }
}
