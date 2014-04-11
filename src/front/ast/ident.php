<?php

namespace phs\front\ast;

class Ident extends Node
{
  public $value;
  
  // symbol lookup cache
  public $symbol;
  
  public function __construct($value)
  {
    $this->value = $value;
  }
}
