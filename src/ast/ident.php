<?php

namespace phs\ast;

class Ident extends Node
{
  public $value;
  
  public function __construct($value)
  {
    $this->value = $value;
  }
}
