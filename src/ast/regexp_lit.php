<?php

namespace phs\ast;

class RegexpLit extends Node
{
  public $value;
  
  public function __construct($value)
  {
    $this->value = $value;
  }
}
