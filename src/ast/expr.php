<?php

namespace phs\ast;

use phs\Location;

abstract class Expr extends Node
{
  // @var Value  if the expression is reducible at compile-time
  public $value;
  
  // @var TypeSet  inferred types
  public $types;
  
  public function __clone()
  {
    if ($this->value)
      $this->value = clone $this->value;
    
    parent::__clone();
  }
}
